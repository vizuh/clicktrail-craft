<?php

declare(strict_types=1);

namespace ClickTrail\Craft\Models;

use craft\base\Model;

class Settings extends Model
{
    /** Site identifier sent with every payload. */
    public ?string $siteId = null;

    /** ClickTrail ingestion endpoint URL. */
    public ?string $endpoint = null;

    /** When true, capture/persist only while host consent state permits. */
    public bool $consentRequired = false;

    /** Serve the first-party loader/proxy from this site's own domain. */
    public bool $firstPartyProxy = false;

    /** Which platform events map to which ClickTrail events. */
    public bool $mapFormSubmissions = true;   // form submit      -> lead.submitted
    public bool $mapUserRegistrations = true; // registration     -> lead.submitted
    public bool $mapCommerceOrders = true;    // order completed  -> sale.completed
    public bool $mapRefunds = true;           // order refunded   -> sale.refunded

    public function defineRules(): array
    {
        return [
            [['siteId', 'endpoint'], 'string'],
            [['endpoint'], 'url', 'defaultScheme' => 'https'],
            [['endpoint'], 'required'],
            [['consentRequired', 'firstPartyProxy',
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
            'lead.submitted' => $this->mapFormSubmissions || $this->mapUserRegistrations,
            'sale.completed' => $this->mapCommerceOrders,
            'sale.refunded' => $this->mapRefunds,
        ];
    }
}
