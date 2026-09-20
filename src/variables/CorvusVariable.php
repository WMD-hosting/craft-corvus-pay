<?php

namespace wmd\craftcorvuspay\variables;

use Craft;
use craft\elements\Asset;
use wmd\craftcorvuspay\CorvusPay;

/**
 * `craft.corvus` in templates.
 */
class CorvusVariable
{
    /**
     * The logo asset chosen in the plugin settings, for the payment method list.
     */
    public function getLogo(): ?Asset
    {
        $ids = CorvusPay::getInstance()->getSettings()->logoId;

        if (empty($ids)) {
            return null;
        }

        return Craft::$app->getAssets()->getAssetById((int)$ids[0]);
    }
}
