<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Services;

use ClickTrail\Consent\ConsentBehavior;
use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Craft\Plugin;
use ClickTrail\Craft\Services\Consent\ConsentResolverInterface;
use ClickTrail\Craft\Services\Consent\NullConsentResolver;
use Craft;

/**
 * Adapter-side gate around the shared ConsentBehavior matrix.
 *
 * Settings decide which capability requires which signal:
 *   - attribution persistence requires analytics_storage;
 *   - ad click-ID storage requires advertising_storage;
 *   - hashed-lead forwarding additionally requires the ad_user_data flag.
 *
 * On any denied/unknown signal the caller must not store or send; the
 * suppressionReason() lands in diagnostics for the audit trail. The resolved
 * snapshot is persisted alongside the attribution state so every submission
 * carries the consent decision it was captured under.
 */
final class ConsentGate
{
    public const SESSION_SNAPSHOT_KEY = 'clicktrail.consent_snapshot';
    public const SESSION_SUPPRESSION_KEY = 'clicktrail.consent_suppression';

    /** Resolve the current snapshot (configured resolver or NullResolver)
     * and persist it next to the attribution state. */
    public static function resolve(): ConsentSnapshot
    {
        $snapshot = self::resolver()->currentSnapshot()
            ?? (new NullConsentResolver())->currentSnapshot();

        $session = Craft::$app->session;
        if ($session->getIsActive()) {
            $session->set(self::SESSION_SNAPSHOT_KEY, $snapshot->toJson());
        }

        return $snapshot;
    }

    public static function storedSnapshot(): ?ConsentSnapshot
    {
        $session = Craft::$app->session;
        if (!$session->getIsActive()) {
            return null;
        }
        $json = $session->get(self::SESSION_SNAPSHOT_KEY);

        return $json === null ? null : ConsentSnapshot::fromJson((string) $json);
    }

    /**
     * Whether the given capability is permitted under the current settings
     * and snapshot. Records suppressionReason() into diagnostics when blocked.
     */
    public static function allows(string $capability): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        // Toggle off = this use does not require CMP consent (site's own basis).
        if ($capability === ConsentSnapshot::CAP_ANALYTICS && !$settings->requireAnalyticsStorage) {
            return true;
        }
        if ($capability === ConsentSnapshot::CAP_ADVERTISING_STORAGE && !$settings->requireAdvertisingStorage) {
            return true;
        }

        $snapshot = self::resolve();

        if (ConsentBehavior::can($snapshot, $capability)) {
            return true;
        }

        self::recordSuppression(
            (string) ConsentBehavior::suppressionReason($snapshot, $capability)
        );

        return false;
    }

    /**
     * Hashed-lead forwarding gate: disabled by default, and when enabled it
     * still needs an explicit granted ad_user_data signal.
     */
    public static function hashedLeadForwardingAllowed(): bool
    {
        if (!Plugin::getInstance()->getSettings()->forwardHashedLeadData) {
            self::recordSuppression('Hashed-lead forwarding is disabled in settings');

            return false;
        }

        return self::allows(ConsentSnapshot::CAP_AD_USER_DATA);
    }

    /** Diagnostics: audit-trail reason for a suppressed action. */
    public static function recordSuppression(string $reason): void
    {
        $session = Craft::$app->session;
        if ($session->getIsActive()) {
            $session->set(self::SESSION_SUPPRESSION_KEY, $reason);
        }
        Craft::info('ClickTrail suppressed: ' . $reason, __METHOD__);
    }

    private static function resolver(): ConsentResolverInterface
    {
        $class = trim((string) (Plugin::getInstance()->getSettings()->consentResolverClass ?? ''));

        if ($class !== '') {
            if (!class_exists($class) || !is_subclass_of($class, ConsentResolverInterface::class)) {
                Craft::warning('ClickTrail consentResolverClass is set but missing or invalid; falling back to unknown-consent behavior', __METHOD__);

                return new NullConsentResolver();
            }
            try {
                /** @var ConsentResolverInterface $instance */
                $instance = new $class();

                return $instance;
            } catch (\Throwable $e) {
                Craft::warning('ClickTrail consent resolver failed to instantiate: ' . $e->getMessage(), __METHOD__);
            }
        }

        return new NullConsentResolver();
    }
}
