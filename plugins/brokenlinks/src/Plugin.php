<?php

namespace craigclement\craftbrokenlinks;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        // Register CP route for the index page
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['brokenlinks'] = 'brokenlinks/broken-links/index';
            }
        );

        // Register route for the crawl action
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['brokenlinks/run-crawl'] = 'brokenlinks/broken-links/run-crawl';
            }
        );

        Craft::info('Broken Links plugin loaded', __METHOD__);
    }
}
