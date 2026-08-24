<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Services;

use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\PayloadSerializer;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use ClickTrail\Core\TouchParser;
use Craft;
use craft\base\Component;

/**
 * Session-backed adapter around the deterministic php-sdk core.
 * All parse/classify/merge/serialize logic lives in ClickTrail\Core\*;
 * this service only supplies effects: request input, session persistence.
 */
class AttributionService extends Component
{
    private const SESSION_KEY = 'clicktrail.attribution';

    /**
     * Observe the current request and merge any new touch into stored state.
     * Call once per request from a site bootstrap/event when consent allows.
     */
    public function captureRequest(): StoredState
    {
        $stored = $this->getStoredState();

        $request = Craft::$app->request;
        if ($request->getIsConsoleRequest()) {
            return $stored;
        }

        $input = new AttributionInput(
            query: (array) $request->getQueryParams(),
            host: (string) $request->hostName,
            landingPage: $request->absoluteUrl,
            referrer: $request->referrer,
            touchTimestamp: gmdate('Y-m-d\TH:i:s.v\Z'),
        );

        $merged = TouchMerger::observe($stored, $input);
        $this->persist($merged);

        return $merged;
    }

    public function getStoredState(): StoredState
    {
        $session = Craft::$app->session;

        if (!$session->getIsActive()) {
            return StoredState::empty();
        }

        return StoredState::fromJson($session->get(self::SESSION_KEY));
    }

    public function persist(StoredState $state): void
    {
        $session = Craft::$app->session;

        if (!$session->getIsActive()) {
            return;
        }

        $session->set(self::SESSION_KEY, $state->toJson());
    }

    /**
     * Build the canonical flat payload for one server-side event.
     *
     * @param array<string, mixed> $event name plus optional id/value/currency etc.
     * @param array<string, mixed> $extra additional top-level payload keys
     * @return array<string, mixed>
     */
    public function buildPayload(string $eventName, array $event = [], array $extra = []): array
    {
        $settings = \ClickTrail\Craft\Plugin::getInstance()->getSettings();

        $attribution = $this->captureRequest();

        return (new PayloadSerializer())->serialize(
            siteId: (string) ($settings->siteId ?? ''),
            event: ['name' => $eventName] + $event,
            attribution: $attribution,
            extra: $extra,
        );
    }
}
