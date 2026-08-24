<?php

declare(strict_types=1);

namespace ClickTrail\Craft\EventListeners;

use Craft;
use GuzzleHttp\Client;

/**
 * Minimal delivery shim. Real transport (retries with backoff, idempotency
 * keys, PSR-18 integration) belongs to clicktrail/php-sdk's Client; until the
 * SDK wiring lands this queues payloads to logs so nothing is lost silently.
 */
class Delivery
{
    public static function send(array $payload): void
    {
        $settings = \ClickTrail\Craft\Plugin::getInstance()->getSettings();

        if (empty($settings->endpoint)) {
            return;
        }

        try {
            // TODO: swap for ClickTrail\Client\Client once SDK transport wiring
            // is finalized (retryable/permanent exception handling included).
            (new Client(['timeout' => 3]))->post(
                rtrim((string) $settings->endpoint, '/') . '/events',
                ['json' => $payload]
            );
        } catch (\Throwable $e) {
            Craft::warning('ClickTrail delivery failed: ' . $e->getMessage(), __METHOD__);
        }
    }
}
