English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/craft-clicktrail**

See which campaign, keyword, click ID and landing page created each form submission, customer and Commerce order in Craft CMS.

</div>

[![CI](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/craft-clicktrail)](https://packagist.org/packages/vizuh/craft-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Index

- [Why](#why)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Event mapping](#event-mapping)
- [Settings](#settings)
- [Consent](#consent)
- [Delivery](#delivery)
- [How it differs](#how-it-differs)
- [Testing](#testing)
- [License](#license)

## Why

Not another analytics script. ClickTrail attaches deterministic first-touch / last-touch attribution to every lead and sale your Craft site produces and ships it server-side to your ClickTrail endpoint — so the answer to "where did this customer come from?" lives next to the record, not in a separate dashboard.

Attribution logic is never reimplemented here; the shared [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) core computes every payload.

Requires Craft CMS 5.0+ and PHP 8.2+. Optional: the Craft Forms plugin (form submissions) and Craft Commerce (orders).

## Installation

```bash
composer require vizuh/craft-clicktrail
```

Then install from Settings → Plugins, or:

```bash
php craft plugin/install clicktrail
```

## Quick start

Read attribution directly in any site template:

```twig
{{ clicktrail.attribution.first.source }}
{# "google" right after a paid-search landing —
   and still "google" after any number of later direct visits #}

<pre>{{ clicktrail.payload('page_view') | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
{# canonical flat payload: schema_version-stamped, dotted attribution.* keys,
   consent snapshot included. Renders [] when analytics consent is unknown/denied. #}
```

A visitor arrives from a Google Ads ad, registers, then completes a Commerce order. Your ClickTrail endpoint receives three canonical events — `lead.submitted`, `lead.submitted`, `sale.completed` — each stamped with the same immutable first touch (`attribution.first.source === 'google'`, click ID preserved) plus the last touch at event time.

## Event mapping

Platform-native events map onto canonical ClickTrail events:

| Craft event | ClickTrail event |
|---|---|
| Form submit (Forms plugin) | `lead.submitted` |
| User registration | `lead.submitted` |
| Commerce order completed | `sale.completed` |
| Commerce order refunded | `sale.refunded` |

Each mapping can be switched off individually in the settings.

## Settings

All options live on the plugin settings page (Settings → ClickTrail):

| Setting | Default | Purpose |
|---|---|---|
| Site ID | empty | Identifies this site to your ClickTrail account |
| Endpoint URL | empty | Where payloads are POSTed |
| Consent resolver class | empty | Custom `ConsentResolverInterface` implementation returning the normalized snapshot; empty = all signals "unknown" |
| Attribution persistence requires `analytics_storage` | on | Store nothing without granted analytics consent |
| Ad click-ID storage requires `advertising_storage` | on | Strip gclid/fbclid/... from storage without advertising consent |
| Send hashed lead data to ad destinations (`ad_user_data`) | off | Extra gate for hashed-lead forwarding; still needs `ad_user_data` granted |
| First-party proxy | off | Serve the ClickTrail loader from your own domain |
| Map form submissions | on | Emit `lead.submitted` on form submits |
| Map user registrations | on | Emit `lead.submitted` on registration |
| Map Commerce orders | on | Emit `sale.completed` on order completion |
| Map refunds | on | Emit `sale.refunded` |

## Consent

ClickTrail does not replace your consent platform — it obeys it. The normalized consent contract (capabilities, snapshot shape, behavior matrix) lives in [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Provider: implement `ClickTrail\Craft\Services\Consent\ConsentResolverInterface` (returns the current `ClickTrail\Consent\ConsentSnapshot`) and point the plugin setting at it. Real CMP adapters are deferred; the WordPress plugin reads WP Consent API directly.
- On unknown consent: **do not store or send**. Suppressed actions are recorded with `suppressionReason()` into diagnostics.
- The resolved snapshot is persisted alongside the attribution state and travels with every event (`consent` key on each payload).

## Delivery

Payloads are POSTed as JSON to `<endpoint>/events`. Failed deliveries are logged as warnings so nothing disappears silently. Full transport (retries with backoff, idempotency keys) belongs to the shared SDK client once its wiring lands.

## How it differs

| Typical analytics setup | ClickTrail for Craft |
|---|---|
| Sessions and pages in a dashboard | Campaign, keyword, click ID and landing page **on each submission, customer and order** |
| Client-side tags you maintain yourself | One Twig variable, one first-party loader |
| Attribution logic duplicated per platform | One deterministic engine, fixture-tested across WordPress, GTM and PHP integrations |

## Testing

GitHub Actions CI lints all PHP files on every push ([workflow](https://github.com/vizuh/clicktrail-craft/blob/main/.github/workflows/ci.yml)).

## License

MIT — Copyright (c) 2026 Vizuh OÜ. See [LICENSE](LICENSE).
