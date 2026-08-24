<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Services\Consent;

use ClickTrail\Consent\ConsentSnapshot;

/**
 * Platform consent hook (normalized contract: docs/consent-compatibility-plan.md).
 *
 * Returns the CURRENT normalized ConsentSnapshot, or null when no CMP state
 * is available - callers then treat every signal as "unknown" (= denied).
 *
 * WordPress ClickTrail builds read WP Consent API directly; on Craft this
 * interface is the custom-resolver hook that can be pointed at your CMP's
 * server-side state. Real CMP adapters are DEFERRED and are NOT part of this
 * plugin - ship your own implementation for CookieYes/Cookiebot/iubenda/...
 */
interface ConsentResolverInterface
{
    public function currentSnapshot(): ?ConsentSnapshot;
}
