<?php

declare(strict_types=1);

namespace ClickTrail\Craft;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTemplatesEvent;
use craft\web\View;
use ClickTrail\Craft\Models\Settings;
use ClickTrail\Craft\Services\AttributionService;
use ClickTrail\Craft\Twig\AttributionExtension;
use yii\base\Event;

/**
 * ClickTrail - attribution connector for Craft Forms and Commerce.
 *
 * The plugin owns effects (session storage, request access, HTTP delivery)
 * and delegates all parse/merge/serialize logic to clicktrail/php-sdk.
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'attribution' => AttributionService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Expose the `clicktrail` variable + functions in site templates.
        $this->getView()->registerTwigExtension(new AttributionExtension());

        EventListeners\UserRegistrations::attach();
        EventListeners\CommerceOrders::attach();

        if (class_exists('\craft\forms\Plugin')) {
            EventListeners\FormSubmissions::attach();
        }
    }

    public function createSettingsModel(): ?craft\base\Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return \Craft::$app->view->renderTemplate('clicktrail/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
