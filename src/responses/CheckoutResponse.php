<?php

namespace wmd\craftcorvuspay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\helpers\App;
use craft\helpers\Html;
use wmd\craftcorvuspay\CorvusPay;
use wmd\craftcorvuspay\models\Settings;

/**
 * Response for the offsite flow. On purchase it is a POST redirect to the
 * CorvusPay hosted checkout carrying the signed parameters; on completion it
 * reports success (the post-back controller has already verified everything).
 */
class CheckoutResponse implements RequestResponseInterface
{
    public const STATUS_REDIRECT = 'redirect';
    public const STATUS_SUCCESSFUL = 'successful';

    /** Field length limits from the CorvusPay integration manual. */
    private const LIMITS = [
        'cardholder_name' => 40,
        'cardholder_surname' => 40,
        'cardholder_address' => 100,
        'cardholder_city' => 20,
        'cardholder_zip_code' => 9,
        'cardholder_country' => 30,
        'cardholder_email' => 100,
    ];

    private ?array $params = null;

    public function __construct(private Transaction $transaction, private string $status = self::STATUS_REDIRECT)
    {
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }

    public function isProcessing(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return $this->status === self::STATUS_REDIRECT;
    }

    public function getRedirectMethod(): string
    {
        return 'POST';
    }

    public function getRedirectUrl(): string
    {
        $base = (string)App::parseEnv(CorvusPay::getInstance()->getSettings()->storeUrl);

        return rtrim($base, '/') . '/checkout/';
    }

    public function getRedirectData(): array
    {
        return $this->getData();
    }

    public function getTransactionReference(): string
    {
        return (string)$this->transaction->hash;
    }

    public function getCode(): string
    {
        return '';
    }

    public function getMessage(): string
    {
        return '';
    }

    /**
     * The signed parameter set for the hosted checkout.
     */
    public function getData(): array
    {
        if ($this->params !== null) {
            return $this->params;
        }

        $settings = CorvusPay::getInstance()->getSettings();
        $order = $this->transaction->getOrder();
        $amount = number_format((float)str_replace(',', '', (string)$this->transaction->paymentAmount), 2, '.', '');

        $params = [
            'amount' => $amount,
            'cart' => 'orderId ' . $this->transaction->orderId,
            'currency' => $this->transaction->paymentCurrency,
            'language' => strtolower(substr(Craft::$app->getSites()->getCurrentSite()->language ?: 'hr', 0, 2)),
            'order_number' => (string)$this->transaction->orderId,
            'require_complete' => 'false',
            'store_id' => (string)App::parseEnv($settings->storeId),
            'version' => '1.4',
        ];

        $params += $this->installmentParams($settings, (float)$amount);
        $params += $this->cardholderParams($order);

        $params['signature'] = CorvusPay::getInstance()->corvusService->calculateSignature($params);

        return $this->params = $params;
    }

    /**
     * Auto-submitting form, used when the site has no gatewayPostRedirectTemplate.
     */
    public function redirect(): void
    {
        $inputs = '';
        foreach ($this->getData() as $name => $value) {
            $inputs .= Html::hiddenInput($name, (string)$value);
        }

        $url = Html::encode($this->getRedirectUrl());
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>CorvusPay</title></head><body>'
            . '<form id="corvus-redirect" action="' . $url . '" method="post">' . $inputs
            . '<noscript><button type="submit">' . Html::encode(Craft::t('corvus-pay', 'Continue to payment')) . '</button></noscript>'
            . '</form><script>document.getElementById("corvus-redirect").submit();</script></body></html>';

        $response = Craft::$app->getResponse();
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->content = $html;
        $response->send();
        Craft::$app->end();
    }

    /**
     * Only one installment variant is sent per request, following CorvusPay's
     * precedence rules.
     */
    private function installmentParams(Settings $settings, float $amount): array
    {
        switch ($settings->installmentsMode) {
            case Settings::INSTALLMENTS_FIXED:
                $value = trim($settings->numberOfInstallments);
                return $value !== '' ? ['number_of_installments' => $value] : [];

            case Settings::INSTALLMENTS_FLEXIBLE:
                $value = trim($settings->paymentAll);
                return $value !== '' ? ['payment_all' => $value] : [];

            case Settings::INSTALLMENTS_TIERED:
                if ($amount < (float)$settings->paymentAllTier1Threshold) {
                    $value = $settings->paymentAllTier1;
                } elseif ($amount < (float)$settings->paymentAllTier2Threshold) {
                    $value = $settings->paymentAllTier2;
                } else {
                    $value = $settings->paymentAllTier3;
                }
                $value = trim($value);
                return $value !== '' ? ['payment_all' => $value] : [];

            case Settings::INSTALLMENTS_DYNAMIC:
                $params = ['payment_all_dynamic' => 'true'];
                foreach (preg_split('/\r?\n/', $settings->paymentBrandParams) ?: [] as $line) {
                    [$key, $value] = array_pad(explode('=', trim($line), 2), 2, '');
                    $key = trim($key);
                    $value = trim($value);
                    if ($key !== '' && $value !== '' && str_starts_with($key, 'payment_')) {
                        $params[$key] = $value;
                    }
                }
                return $params;
        }

        return [];
    }

    /**
     * Optional cardholder fields, truncated to the manual's limits.
     */
    private function cardholderParams($order): array
    {
        $params = [];

        if (!empty($order->email)) {
            $params['cardholder_email'] = $this->limit($order->email, 'cardholder_email');
        }

        $billing = $order->getBillingAddress();
        if (!$billing) {
            return $params;
        }

        $first = trim((string)$billing->firstName);
        $last = trim((string)$billing->lastName);
        if (($first === '' || $last === '') && !empty($billing->fullName)) {
            $parts = preg_split('/\s+/', trim((string)$billing->fullName)) ?: [];
            if (count($parts) > 1) {
                $last = $last ?: (string)array_pop($parts);
                $first = $first ?: implode(' ', $parts);
            } else {
                $first = $first ?: (string)($parts[0] ?? '');
            }
        }
        if ($first !== '') {
            $params['cardholder_name'] = $this->limit($first, 'cardholder_name');
        }
        if ($last !== '') {
            $params['cardholder_surname'] = $this->limit($last, 'cardholder_surname');
        }

        $address = trim(trim((string)$billing->addressLine1) . ' ' . trim((string)$billing->addressLine2));
        if ($address !== '') {
            $params['cardholder_address'] = $this->limit($address, 'cardholder_address');
        }
        if (!empty($billing->locality)) {
            $params['cardholder_city'] = $this->limit($billing->locality, 'cardholder_city');
        }
        if (!empty($billing->postalCode)) {
            $params['cardholder_zip_code'] = $this->limit($billing->postalCode, 'cardholder_zip_code');
        }
        if (!empty($billing->countryCode)) {
            $code = strtoupper(substr((string)$billing->countryCode, 0, 2));
            $params['cardholder_country_code'] = $code;
            $name = Craft::$app->getAddresses()->getCountryRepository()->get($code)?->getName() ?: $code;
            $params['cardholder_country'] = $this->limit($name, 'cardholder_country');
        }

        return $params;
    }

    private function limit(mixed $value, string $field): string
    {
        return mb_substr(trim((string)$value), 0, self::LIMITS[$field]);
    }
}
