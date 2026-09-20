<?php

namespace wmd\craftcorvuspay\models;

use craft\base\Model;

/**
 * Plugin settings. Secrets and file paths accept environment variables
 * (`$CORVUS_SECRET_KEY`) and aliases (`@root/certs/corvus.crt.pem`).
 */
class Settings extends Model
{
    public const INSTALLMENTS_NONE = 'none';
    public const INSTALLMENTS_FIXED = 'fixed';
    public const INSTALLMENTS_FLEXIBLE = 'flexible';
    public const INSTALLMENTS_TIERED = 'flexibleTiered';
    public const INSTALLMENTS_DYNAMIC = 'dynamic';

    /** @var string Store ID issued by CorvusPay. */
    public string $storeId = '';

    /** @var string Secret key issued by CorvusPay. */
    public string $secretKey = '';

    /** @var string Hosted checkout base URL: test-wallet.corvuspay.com or wallet.corvuspay.com. */
    public string $storeUrl = 'https://test-wallet.corvuspay.com/';

    /** @var string Merchant API base URL: testcps.corvus.hr or cps.corvus.hr. */
    public string $storeUrlStatus = 'https://testcps.corvus.hr/';

    /** @var string Path to the client certificate (PEM). */
    public string $pathToFileCrtPem = '';

    /** @var string Path to the client private key (PEM). */
    public string $pathToFileKeyPem = '';

    /** @var string Password protecting the private key. */
    public string $keyForFiles = '';

    /** @var string Where the customer lands after a successful payment when the order has no return URL. */
    public string $successUrl = '';

    /** @var string Where the customer lands after a failed or cancelled payment when the order has no cancel URL. */
    public string $failUrl = '';

    /** @var array Asset ID of the logo shown next to the payment method. */
    public array $logoId = [];

    /** @var string none | fixed | flexible | flexibleTiered | dynamic */
    public string $installmentsMode = self::INSTALLMENTS_NONE;

    /** @var string Fixed mode: number_of_installments, two digits. */
    public string $numberOfInstallments = '';

    /** @var string Flexible mode: payment_all flag, e.g. Y0299. */
    public string $paymentAll = '';

    /** @var string Dynamic mode: one `payment_<brand>=Yxxyy` per line. */
    public string $paymentBrandParams = '';

    public string $paymentAllTier1Threshold = '100';
    public string $paymentAllTier2Threshold = '1000';
    public string $paymentAllTier1 = 'Y0000';
    public string $paymentAllTier2 = 'Y0212';
    public string $paymentAllTier3 = 'Y0224';

    public function rules(): array
    {
        return [
            [['storeId', 'storeUrl', 'storeUrlStatus', 'secretKey'], 'required'],
            [['installmentsMode'], 'in', 'range' => [
                self::INSTALLMENTS_NONE,
                self::INSTALLMENTS_FIXED,
                self::INSTALLMENTS_FLEXIBLE,
                self::INSTALLMENTS_TIERED,
                self::INSTALLMENTS_DYNAMIC,
            ]],
            [['numberOfInstallments'], 'match', 'pattern' => '/^\d{2}$/', 'skipOnEmpty' => true],
            [['paymentAll', 'paymentAllTier1', 'paymentAllTier2', 'paymentAllTier3'], 'match', 'pattern' => '/^Y\d{4}$/', 'skipOnEmpty' => true],
            [['paymentAllTier1Threshold', 'paymentAllTier2Threshold'], 'number', 'min' => 0],
        ];
    }
}
