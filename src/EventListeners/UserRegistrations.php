<?php

declare(strict_types=1);

namespace ClickTrail\Craft\EventListeners;

use ClickTrail\Conventions\Stable;
use craft\elements\User;
use yii\base\Event;
use craft\events\ModelEvent;

/**
 * User registration -> lead_created.
 */
class UserRegistrations
{
    public static function attach(): void
    {
        Event::on(User::class, User::EVENT_AFTER_SAVE, self::class . '::onUserSave');
    }

    public static function onUserSave(ModelEvent $event): void
    {
        if (!$event->isNew) {
            return;
        }

        $plugin = \ClickTrail\Craft\Plugin::getInstance();
        $settings = $plugin->getSettings();

        if (!$settings->mapUserRegistrations) {
            return;
        }

        /** @var User $user */
        $user = $event->sender;

        $payload = $plugin->get('attribution')->buildPayload(
            Stable::EVENT_LEAD_CREATED,
            event: [],
            extra: [
                'object_type' => 'user',
                'object_id' => (string) $user->id,
            ],
        );

        Delivery::send($payload);
    }
}
