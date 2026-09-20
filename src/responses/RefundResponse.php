<?php

namespace wmd\craftcorvuspay\responses;


use craft\commerce\base\RequestResponseInterface;

class RefundResponse implements RequestResponseInterface
{

    protected $data;

    /**
     * Construct the response
     *
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Returns whether the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        $data = $this->data;
//        dd($data);
        if (isset($data['status'])
            and isset($data['response-message'])
            and $data['status'] == 'partially_refunded'
            and $data['response-message'] == 'approved'){
            return true;
        }else if(isset($data['status'])
            and isset($data['response-message'])
            and $data['status'] == 'voided'
            and $data['response-message'] == 'approved') {
            return true;
        }else{
            return false;
        }
    }

    /**
     * Returns whether the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return false;
    }

    /**
     * Returns whether the user needs to be redirected.
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * Returns the redirect method to use, if any.
     *
     * @return string
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * Returns the redirect data provided.
     *
     * @return array
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * Returns the redirect URL to use, if any.
     *
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * Returns the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string
    {
        return '';
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return '';
    }

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Returns the gateway message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return '';
    }

    /**
     * Perform the redirect.
     */
    public function redirect(): void
    {
    }
}