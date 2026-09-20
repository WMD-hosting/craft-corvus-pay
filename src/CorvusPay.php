<?php

namespace wmd\craftcorvuspay;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\web\twig\variables\CraftVariable;
use wmd\craftcorvuspay\gateways\CorvusGateway;
use wmd\craftcorvuspay\models\Settings;
use wmd\craftcorvuspay\services\CorvusService;
use wmd\craftcorvuspay\variables\CorvusVariable;
use yii\base\Event;

/**
 * CorvusPay gateway for Craft Commerce.
 *
 * @method static CorvusPay getInstance()
 * @method Settings getSettings()
 * @property-read CorvusService $corvusService
 */
class CorvusPay extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['corvusService' => CorvusService::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;
                $variable->set('corvus', CorvusVariable::class);
            }
        );

        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = CorvusGateway::class;
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('corvus-pay/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
