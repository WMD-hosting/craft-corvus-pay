<?php

namespace wmd\craftcorvuspay\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;
use craft\web\View;
use wmd\craftcorvuspay\CorvusPay;
use wmd\craftcorvuspay\responses\CheckoutResponse;
use wmd\craftcorvuspay\responses\RefundResponse;
use yii\base\NotSupportedException;

/**
 * Offsite gateway: the customer is sent to the CorvusPay hosted checkout with a
 * signed POST, CorvusPay posts back to the plugin's success or cancel action.
 */
class CorvusGateway extends BaseGateway
{
    public static function displayName(): string
    {
        return Craft::t('corvus-pay', 'CorvusPay');
    }

    public function getPaymentFormHtml(array $params): ?string
    {
        $view = Craft::$app->getView();
        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        $html = $view->renderTemplate('corvus-pay/form.twig', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new CheckoutResponse($transaction);
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new CheckoutResponse($transaction, CheckoutResponse::STATUS_SUCCESSFUL);
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Authorizing is not supported by this gateway'));
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Capturing is not supported by this gateway'));
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Authorizing is not supported by this gateway'));
    }

    /**
     * A full refund voids the CorvusPay transaction; anything less sets a new
     * (lower) amount through partial_refund, as the CorvusPay API defines it.
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $service = CorvusPay::getInstance()->corvusService;
        $order = $transaction->getOrder();
        $amountToRefund = (float)$transaction->amount;
        $totalPaid = (float)$order->storedTotalPaid;

        if (abs($totalPaid - $amountToRefund) < 0.005) {
            return new RefundResponse($service->totalRefund($order));
        }

        return new RefundResponse($service->partialRefund($totalPaid - $amountToRefund, $order, $transaction->currency));
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotSupportedException(Craft::t('commerce', 'Payment sources are not supported by this gateway'));
    }

    public function deletePaymentSource(string $token): bool
    {
        throw new NotSupportedException(Craft::t('commerce', 'Payment sources are not supported by this gateway'));
    }

    public function processWebHook(): WebResponse
    {
        throw new NotSupportedException(Craft::t('commerce', 'Webhooks are not supported by this gateway'));
    }

    public function supportsAuthorize(): bool
    {
        return false;
    }

    public function supportsCapture(): bool
    {
        return false;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsPurchase(): bool
    {
        return true;
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function supportsPartialPayment(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }
}
