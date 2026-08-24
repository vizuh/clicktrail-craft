# ClickTrail for Craft CMS

**See which campaign, keyword, click ID and landing page created each form submission, customer and Commerce order.**

This is not another analytics script. ClickTrail attaches deterministic
first-touch / last-touch attribution data to every lead and sale your Craft
site produces, and ships it to your ClickTrail endpoint server-side - so the
answer to "where did this customer come from?" lives next to the record,
not in a separate dashboard.

## What it does

- Captures UTMs, ad click IDs (gclid, fbclid, msclkid, ttclid, ...) and
  referrer signals on first visit; merges them under the same laws as the
  shared ClickTrail engine (first touch immutable, click-ID-aware guard,
  last-non-direct).
- Persists attribution in the Craft session; nothing is sent until the
  visitor produces a real event.
- Maps platform events to ClickTrail events:

  | Craft event | ClickTrail event |
  |---|---|
  | Form submit (Forms plugin) | `lead.submitted` |
  | User registration | `lead.submitted` |
  | Commerce order completed | `sale.completed` |
  | Commerce order refunded | `sale.refunded` |

- Sends canonical flat payloads (`schema_version`-stamped, dotted
  `attribution.*` keys) built by the shared
  [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) core.
  Attribution logic is never reimplemented here.

## Requirements

- Craft CMS 5.0+
- PHP 8.2+
- Optional: Craft Forms plugin (form submissions), Craft Commerce (orders)

## Installation

```
composer require vizuh/craft-clicktrail
```

Then install from Settings -> Plugins, or:

```
php craft plugin/install clicktrail
```

## Configuration

All options live on the plugin settings page (Settings -> ClickTrail):

| Setting | Default | Purpose |
|---|---|---|
| Site ID | empty | Identifies this site in your ClickTrail endpoint |
| Endpoint URL | empty | Where payloads are POSTed |
| Consent resolver class | empty | Custom `ConsentResolverInterface` implementation returning the normalized snapshot; empty = all signals "unknown" |
| Attribution persistence requires `analytics_storage` | on | Gate attribution storage on granted analytics consent |
| Ad click-ID storage requires `advertising_storage` | on | Gate ad click-ID (gclid, fbclid, ...) storage on granted advertising consent |
| Send hashed lead data to ad destinations (`ad_user_data`) | off | Additional gate for hashed-lead forwarding; still needs `ad_user_data` granted |
| First-party proxy | off | Serve the ClickTrail loader from your own domain |
| Map form submissions | on | Emit `lead.submitted` on form submits |
| Map user registrations | on | Emit `lead.submitted` on registration |
| Map Commerce orders | on | Emit `sale.completed` on order completion |
| Map refunds | on | Emit `sale.refunded` |

## Template API

```twig
{# full attribution object (first + last touch) #}
{{ clicktrail.attribution.first.source }}
{{ clicktrail.attribution.last.landingPage }}

{# canonical payload for the current visitor #}
<pre>{{ clicktrail.payload('page_view') | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
```

## Consent

ClickTrail does not replace your consent platform - it obeys it. The full
normalized consent contract (capabilities, snapshot shape, behavior matrix)
lives in [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Provider: auto-detect through a custom resolver class. WordPress ClickTrail
  builds read WP Consent API directly; on Craft, implement
  `ClickTrail\\Craft\\Services\\Consent\\ConsentResolverInterface`
  (returns the current `ClickTrail\\Consent\\ConsentSnapshot`) and point
  the plugin setting at it. Real CMP adapters are deferred.
- Attribution persistence requires `analytics_storage`; ad click-ID storage
  requires `advertising_storage`; hashed-lead forwarding additionally needs an
  explicit granted `ad_user_data` signal (disabled by default).
- On unknown consent: **do not store or send**. Suppressed actions are recorded
  with `suppressionReason()` into diagnostics.
- The resolved consent snapshot is persisted alongside the attribution state and
  travels with every submission (`consent` key on each payload).

## License

MIT - Copyright (c) 2026 Vizuh OU. See [LICENSE](LICENSE).
