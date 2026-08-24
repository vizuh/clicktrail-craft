<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Twig;

use ClickTrail\Core\StoredState;

/**
 * Template-facing read-only view of the current visitor attribution state.
 */
class AttributionVariable
{
    public function attribution(): StoredState
    {
        return \ClickTrail\Craft\Plugin::getInstance()->get('attribution')->captureRequest();
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function payload(string $eventName, array $event = []): array
    {
        return \ClickTrail\Craft\Plugin::getInstance()->get('attribution')->buildPayload($eventName, $event);
    }
}
