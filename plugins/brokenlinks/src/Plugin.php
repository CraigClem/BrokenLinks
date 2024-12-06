<?php

namespace craigclement\craftbrokenlinks;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use yii\base\Event; 
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;

/**
 * Broken Links plugin
 *
 * @method static Plugin getInstance()
 * @author Craig Clement
 * @copyright Craig Clement
 * @license https://craftcms.github.io/license/ Craft License
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'linkCheckerService' => [
                    'class' => \craigclement\craftbrokenlinks\services\LinkCheckerService::class,
                ],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

            // Add a CP navigation section
            Event::on(
                Cp::class,
                Cp::EVENT_REGISTER_CP_NAV_ITEMS,
                function (RegisterCpNavItemsEvent $event) {
                    $event->navItems[] = [
                        'url' => 'brokenlinks', // The route for the CP section
                        'label' => 'Broken Links', // Label shown in the CP
                        'icon' => '@appicons/globe.svg', // Optional: Craft's built-in icons
                    ];
                }
            );

            // Register CP routes
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $event->rules['/admin/brokenlinks/broken-links'] = 'brokenlinks/broken-links';
                }
            );

        Craft::info('Broken Links plugin loaded', __METHOD__);
    }

    /**
     * Defines the CP navigation item.
     *
     * @return array|null
     */


    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = 'Broken Links';
        $navItem['url'] = 'brokenlinks'; // The route for your plugin
        $navItem['icon'] = '@appicons/globe.svg'; // Optional: Choose an icon from Craft's icon set
        return $navItem;
    }
}
