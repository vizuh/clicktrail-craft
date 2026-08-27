<?php

declare(strict_types=1);

namespace ClickTrail\Craft\EventListeners;

use Craft;
use ClickTrail\Conventions\Stable;

/**
 * Form submission -> lead_created.
 *
 * Targets the native Craft Forms plugin. The exact event interface and
 * namespace must be confirmed against the installed Forms plugin version.
 */
class FormSubmissions
{
    public static function attach(): void
    {
        if (!class_exists('\craft\forms\Plugin') || !interface_exists('\craft\forms\event\SubmitEventInterface')) {
            return;
        }

        // TODO verify: confirm craft\forms\Submission::EVENT_AFTER_SUBMIT
        // (or the SubmitEventInterface dispatch point) against the Forms
        // plugin release actually targeted.
        \yii\base\Event::on(
            \craft\forms\Submission::class,
            defined('\craft\forms\Submission::EVENT_AFTER_SUBMIT') ? \craft\forms\Submission::EVENT_AFTER_SUBMIT : 'after.submit',
            self::class . '::onSubmit'
        );
    }

    public static function onSubmit(\craft\forms\event\SubmitEventInterface $event): void
    {
        $plugin = \ClickTrail\Craft\Plugin::getInstance();
        $settings = $plugin->getSettings();

        if (!$settings->mapFormSubmissions) {
            return;
        }

        $submission = method_exists($event, 'getSubmission') ? $event->getSubmission() : null;

        $extra = [];
        if ($submission !== null && isset($submission->id)) {
            $extra['object_type'] = 'form_submission';
            $extra['object_id'] = (string) $submission->id;
        }

        // Delivery is delegated to the SDK client; wire endpoint transport here.
        $payload = $plugin->get('attribution')->buildPayload(
            Stable::EVENT_LEAD_CREATED,
            event: [],
            extra: $extra,
        );

        Craft::debug('ClickTrail lead_created payload built', __METHOD__);
        Delivery::send($payload);
    }
}
