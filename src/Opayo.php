<?php
/**
 * Commerce Opayo plugin for Craft CMS 3.x
 *
 * Opayo payment gateway for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2021 Web DNA
 */

namespace webdna\commerce\opayo;

use webdna\commerce\opayo\services\CommerceOpayoService;
use webdna\commerce\opayo\models\Settings;
use webdna\commerce\opayo\gateways\Gateway;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\log\FileTarget;
use yii\log\Logger;
use yii\base\Event;

/**
 * Class CommerceOpayo
 *
 * @author    Web DNA
 * @package   CommerceOpayo
 * @since     1.0.0
 *
 * @property  CommerceOpayoServiceService $commerceOpayoService
 */
class Opayo extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CommerceOpayo
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public $hasCpSettings = false;

    /**
     * @var bool
     */
    public $hasCpSection = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'commerce-opayo/default';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cpActionTrigger1'] = 'commerce-opayo/default/do-something';
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );
        
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/opayo.log'),
            'categories' => ['opayo'],
        ]);

        Craft::info(
            Craft::t(
                'commerce-opayo',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }
    
    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'opayo');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'opayo');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'commerce-opayo/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
