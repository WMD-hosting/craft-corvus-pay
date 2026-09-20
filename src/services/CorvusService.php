<?php

namespace wmd\craftcorvuspay\services;

use Craft;
use craft\commerce\elements\Order;
use craft\helpers\App;
use wmd\craftcorvuspay\CorvusPay;
use yii\base\Component;

/**
 * Signatures and the CorvusPay merchant API (status, refund, partial refund).
 *
 * The merchant API is mutually authenticated: CorvusPay issues a client
 * certificate per store, which the settings point at through a certificate
 * and a key file (or environment variables holding their paths).
 */
class CorvusService extends Component
{
    public const API_VERSION = '1.4';

    /** ISO 4217 numeric codes for the currencies CorvusPay accepts. */
    private const CURRENCY_CODES = [
        'EUR' => 978, 'USD' => 840, 'GBP' => 826, 'CHF' => 756, 'AUD' => 36,
        'CAD' => 124, 'BAM' => 977, 'RSD' => 941, 'CZK' => 203, 'DKK' => 208,
        'HUF' => 348, 'NOK' => 578, 'PLN' => 985, 'SEK' => 752, 'HRK' => 191,
    ];

    private string $secretKey = '';
    private string $storeId = '';
    private string $certificatePath = '';
    private string $keyPath = '';
    private string $keyPassword = '';
    private string $apiUrl = '';

    public function init(): void
    {
        parent::init();

        $settings = CorvusPay::getInstance()->getSettings();
        $this->secretKey = (string)App::parseEnv($settings->secretKey);
        $this->storeId = (string)App::parseEnv($settings->storeId);
        $this->certificatePath = (string)App::parseEnv($settings->pathToFileCrtPem);
        $this->keyPath = (string)App::parseEnv($settings->pathToFileKeyPem);
        $this->keyPassword = (string)App::parseEnv($settings->keyForFiles);
        $this->apiUrl = rtrim((string)App::parseEnv($settings->storeUrlStatus), '/');
    }

    /**
     * Signature for the hosted-checkout POST and for the post-back: keys sorted,
     * concatenated as key+value, HMAC-SHA256 with the store secret.
     */
    public function calculateSignature(array $params): string
    {
        ksort($params);
        $data = '';
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        return hash_hmac('sha256', $data, $this->secretKey);
    }

    /**
     * ISO 4217 numeric code for a currency, or null if CorvusPay does not take it.
     */
    public function currencyCode(string $currency): ?int
    {
        return self::CURRENCY_CODES[strtoupper($currency)] ?? null;
    }

    /**
     * Current status of an order's transaction on CorvusPay.
     */
    public function checkTransactionStatus(Order $order): array
    {
        $timestamp = date('YmdHis');
        $currencyCode = $this->currencyCode($order->currency);

        if ($currencyCode === null) {
            Craft::warning("CorvusPay does not accept currency {$order->currency}.", __METHOD__);
            return [];
        }

        $hash = sha1($this->secretKey . $order->id . $this->storeId . $currencyCode . $timestamp . self::API_VERSION);

        return $this->request('/status', [
            'store_id' => $this->storeId,
            'order_number' => $order->id,
            'hash' => $hash,
            'currency_code' => $currencyCode,
            'timestamp' => $timestamp,
            'version' => self::API_VERSION,
        ]);
    }

    /**
     * Lower the captured amount to $newAmount (CorvusPay "partial refund").
     */
    public function partialRefund(float|string $newAmount, Order $order, string $currency): array
    {
        $newAmount = number_format((float)str_replace(',', '', (string)$newAmount), 2, '.', '');
        $hash = sha1($this->secretKey . $order->id . $this->storeId . self::API_VERSION . $newAmount . $currency);

        return $this->request('/partial_refund', [
            'store_id' => $this->storeId,
            'order_number' => $order->id,
            'hash' => $hash,
            'new_amount' => $newAmount,
            'currency' => $currency,
            'version' => self::API_VERSION,
        ]);
    }

    /**
     * Void the whole transaction (CorvusPay "refund").
     */
    public function totalRefund(Order $order): array
    {
        $hash = sha1($this->secretKey . $order->id . $this->storeId . self::API_VERSION);

        return $this->request('/refund', [
            'store_id' => $this->storeId,
            'order_number' => $order->id,
            'hash' => $hash,
            'version' => self::API_VERSION,
        ]);
    }

    /**
     * POST to the merchant API with the client certificate; the XML reply comes
     * back as an array, or empty on any failure (logged).
     */
    private function request(string $endpoint, array $data): array
    {
        $curl = curl_init($this->apiUrl . $endpoint);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLCERT => $this->certificatePath,
            CURLOPT_SSLKEY => $this->keyPath,
            CURLOPT_SSLCERTPASSWD => $this->keyPassword,
            CURLOPT_SSLKEYPASSWD => $this->keyPassword,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            Craft::error("CorvusPay {$endpoint} request failed: {$error}", __METHOD__);
            return [];
        }

        $xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            Craft::error("CorvusPay {$endpoint} returned unreadable XML.", __METHOD__);
            return [];
        }

        $decoded = json_decode(json_encode($xml), true);

        return is_array($decoded) ? $decoded : [];
    }
}
