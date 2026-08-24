<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Services;

use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Conventions\Stable;
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
 *
 * Consent contract: attribution persistence requires analytics_storage and
 * ad click-ID storage requires advertising_storage (per settings); on
 * denied/unknown nothing is stored or sent and the reason lands in
 * diagnostics. The resolved ConsentSnapshot is persisted alongside the
 * attribution state and attached to every payload under "consent".
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

        if (!ConsentGate::allows(ConsentSnapshot::CAP_ANALYTICS)) {
            // Unknown/denied analytics consent: do not store or send.
            return StoredState::empty();
        }

        $query = (array) $request->getQueryParams();
        if (!ConsentGate::allows(ConsentSnapshot::CAP_ADVERTISING_STORAGE)) {
            // Ad click-ID keys are stripped before they can be persisted.
            $query = array_diff_key($query, array_fill_keys(Stable::CLICK_ID_KEYS, true));
        }

        $input = new AttributionInput(
            query: $query,
            host: (string) $request->hostName,
            landingPage: $request->absoluteUrl,
            referrer: $request->referrer,
            touchTimestamp: gmdate('Y-m-d\TH:i:s.v\Z'),
        );

        $merged = TouchMerger::observe($stored, $input);
        $this->persist($merged);
        ConsentGate::resolve();

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

        if (!ConsentGate::allows(ConsentSnapshot::CAP_ANALYTICS)) {
            // Suppressed before queueing; reason already in diagnostics.
            // Future hashed-lead forwarding must also pass
            // ConsentGate::hashedLeadForwardingAllowed() (ad_user_data).
            return [];
        }

        $attribution = $this->captureRequest();
        ConsentGate::resolve();

        return (new PayloadSerializer())->serialize(
            siteId: (string) ($settings->siteId ?? ''),
            event: ['name' => $eventName] + $event,
            attribution: $attribution,
            extra: $extra + ['consent' => json_decode(ConsentGate::resolve()->toJson(), true)],
        );
    }
}
