<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Models;

use craft\base\Model;

/**
 * Consent contract (docs/consent-compatibility-plan.md): capability toggles
 * decide which use requires which signal; unknown consent stores and sends
 * nothing. The plugin never acts as a CMP.
 */
class Settings extends Model
{
    /** Site identifier sent with every payload. */
    public ?string $siteId = null;

    /** ClickTrail ingestion endpoint URL. */
    public ?string $endpoint = null;

    /**
     * Optional custom resolver class implementing
     * ConsentResolverInterface (returns the current normalized snapshot).
     * Empty = all signals "unknown". Real CMP adapters are deferred;
     * WordPress builds read WP Consent API directly.
     */
    public ?string $consentResolverClass = null;

    /** Attribution persistence requires granted analytics_storage. */
    public bool $requireAnalyticsStorage = true;

    /** Ad click-ID storage requires granted advertising_storage. */
    public bool $requireAdvertisingStorage = true;

    /** Hashed-lead forwarding gate; when enabled still needs ad_user_data granted. */
    public bool $forwardHashedLeadData = false;

    /** Serve the first-party loader/proxy from this site's own domain. */
    public bool $firstPartyProxy = false;

    /** Which platform events map to which ClickTrail events. */
    public bool $mapFormSubmissions = true;   // form submit      -> lead_created
    public bool $mapUserRegistrations = true; // registration     -> lead_created
    public bool $mapCommerceOrders = true;    // order completed  -> sale
    public bool $mapRefunds = true;           // order refunded   -> refund

    public function defineRules(): array
    {
        return [
            [['siteId', 'endpoint', 'consentResolverClass'], 'string'],
            [['endpoint'], 'url', 'defaultScheme' => 'https'],
            [['endpoint'], 'required'],
            [['requireAnalyticsStorage', 'requireAdvertisingStorage', 'forwardHashedLeadData',
              'firstPartyProxy',
              'mapFormSubmissions', 'mapUserRegistrations',
              'mapCommerceOrders', 'mapRefunds'], 'boolean'],
        ];
    }

    /**
     * @return array<string, bool> event-name -> enabled
     */
    public function eventMap(): array
    {
        return [
            'lead_created' => $this->mapFormSubmissions || $this->mapUserRegistrations,
            'sale' => $this->mapCommerceOrders,
            'refund' => $this->mapRefunds,
        ];
    }
}
