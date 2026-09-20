<?php

namespace wmd\craftcorvuspay\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use wmd\craftcorvuspay\CorvusPay;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Post-back endpoints CorvusPay calls after the hosted checkout.
 *
 * Success URL: actions/corvus-pay/payment/success
 * Cancel URL:  actions/corvus-pay/payment/cancel
 */
class PaymentController extends Controller
{
    protected array|int|bool $allowAnonymous = true;
    public $enableCsrfValidation = false;

    /**
     * CorvusPay posts the signed result here. The signature is verified, then
     * the transaction status is read back from the merchant API before the
     * order is marked paid.
     */
    public function actionSuccess(): Response
    {
        $this->requirePostRequest();

        $params = Craft::$app->getRequest()->getBodyParams();
        $signature = (string)($params['signature'] ?? '');
        unset($params['signature']);

        $service = CorvusPay::getInstance()->corvusService;

        if ($signature === '' || !hash_equals($service->calculateSignature($params), $signature)) {
            throw new BadRequestHttpException('CorvusPay signature does not match.');
        }

        $order = $this->order($params);
        $status = $service->checkTransactionStatus($order);
        $params = array_merge($params, $status);

        $authorized = isset($status['status']) && strtolower((string)$status['status']) === 'authorized';
        $approved = !empty($params['approval_code']);

        if (!$authorized && !$approved) {
            $this->record($order, $params, TransactionRecord::STATUS_FAILED);

            return $this->redirect($this->cancelUrl($order));
        }

        $this->record($order, $params, TransactionRecord::STATUS_SUCCESS);

        return $this->redirect($this->returnUrl($order));
    }

    /**
     * The customer abandoned or CorvusPay declined the payment.
     */
    public function actionCancel(): Response
    {
        $this->requirePostRequest();

        $params = Craft::$app->getRequest()->getBodyParams();
        $order = $this->order($params);
        $this->record($order, $params, TransactionRecord::STATUS_FAILED);

        return $this->redirect($this->cancelUrl($order));
    }

    private function order(array $params): Order
    {
        $id = (int)($params['order_number'] ?? 0);
        $order = $id ? Order::find()->id($id)->one() : null;

        if (!$order) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $order;
    }

    /**
     * Close the pending purchase transaction Commerce opened before the
     * redirect with a child transaction carrying CorvusPay's answer.
     */
    private function record(Order $order, array $response, string $status): void
    {
        $transactions = Commerce::getInstance()->getTransactions();
        $parent = $order->getLastTransaction();

        $transaction = $transactions->createTransaction($order, $parent);
        $transaction->type = TransactionRecord::TYPE_PURCHASE;
        $transaction->status = $status;
        $transaction->response = $response;
        $transaction->reference = (string)($response['approval_code'] ?? $parent?->reference ?? '');
        $transaction->message = (string)($response['status'] ?? $status);

        if ($parent) {
            $transaction->amount = $parent->amount;
            $transaction->paymentAmount = $parent->paymentAmount;
            $transaction->currency = $parent->currency;
            $transaction->paymentCurrency = $parent->paymentCurrency;
        }

        $transactions->saveTransaction($transaction);
        $order->updateOrderPaidInformation();
    }

    private function returnUrl(Order $order): string
    {
        $url = (string)$order->returnUrl;
        $settings = CorvusPay::getInstance()->getSettings();

        // Action and component URLs render nothing useful after a POST back.
        if ($url === '' || str_contains($url, '/actions/') || str_contains($url, 'sprig-core/components/render')) {
            $url = (string)App::parseEnv($settings->successUrl);
        }

        return $url !== '' ? $url : UrlHelper::siteUrl();
    }

    private function cancelUrl(Order $order): string
    {
        $url = (string)$order->cancelUrl;

        if ($url === '') {
            $url = (string)App::parseEnv(CorvusPay::getInstance()->getSettings()->failUrl);
        }

        return $url !== '' ? $url : UrlHelper::siteUrl();
    }
}
