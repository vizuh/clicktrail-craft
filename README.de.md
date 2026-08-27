English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/craft-clicktrail**

Übertragen Sie beobachteten Akquisitionskontext in konfigurierte Payloads für
Craft Forms, Benutzer und Commerce-Events.

</div>

[![CI](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/craft-clicktrail)](https://packagist.org/packages/vizuh/craft-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Inhalt

- [Warum](#warum)
- [Installation](#installation)
- [Schnellstart](#schnellstart)
- [Event-Mapping](#event-mapping)
- [Einstellungen](#einstellungen)
- [Consent](#consent)
- [Auslieferung](#auslieferung)
- [Wie es sich unterscheidet](#wie-es-sich-unterscheidet)
- [Tests](#tests)
- [Lizenz](#lizenz)

## Warum

Dieser Connector liest gespeicherten First-Touch- und Last-Touch-Kontext und
erstellt kanonische Payloads für konfigurierte Craft-Formular-, Benutzer- und
Commerce-Events. Er bestimmt nicht, welche Kampagne einen Lead oder Verkauf
verursacht hat. Für die Zustellung gelten die unten dokumentierten
Transportgrenzen.

Die Attributionslogik wird hier nicht neu implementiert. Der gemeinsame Kern
[`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) berechnet jedes
Payload.

Benötigt Craft CMS 5.0+ und PHP 8.2+. Optional: das Craft-Forms-Plugin (Formular-Übermittlungen) und Craft Commerce (Bestellungen).

## Installation

```bash
composer require vizuh/craft-clicktrail
```

Anschließend unter Einstellungen → Plugins installieren, oder:

```bash
php craft plugin/install clicktrail
```

## Schnellstart

Lesen Sie die Attribution direkt in jedem Site-Template:

```twig
{{ clicktrail.attribution.first.source }}
{# "google" direkt nach einer Paid-Search-Landingpage;
   und weiterhin "google" nach beliebig vielen späteren Direktbesuchen #}

<pre>{{ clicktrail.payload('page_view') | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
{# kanonisches flaches Payload: mit schema_version, punktierten attribution.*-Schlüsseln,
   Consent-Snapshot inklusive. Rendert [], wenn Analytics-Consent unknown/denied ist. #}
```

Eine konfigurierte Journey von der Ankunft über die Registrierung bis zur
Commerce-Bestellung kann drei kanonische Events erzeugen: `lead_created`,
`lead_created` und `sale`. Jedes Payload enthält den beobachteten First Touch
und den Last Touch zum Event-Zeitpunkt. Der Zustellungserfolg hängt vom
konfigurierten Endpoint und den unten beschriebenen Transportgrenzen ab.

## Event-Mapping

Plattformnative Events werden auf kanonische ClickTrail-Events abgebildet:

| Craft-Event | ClickTrail-Event |
|---|---|
| Formular-Übermittlung (Forms-Plugin) | `lead_created` |
| Benutzerregistrierung | `lead_created` |
| Commerce-Bestellung abgeschlossen | `sale` |
| Commerce-Bestellung erstattet | `refund` |

Jedes Mapping lässt sich in den Einstellungen einzeln abschalten.

## Einstellungen

Alle Optionen finden Sie auf der Plugin-Einstellungsseite (Einstellungen → ClickTrail):

| Einstellung | Standard | Zweck |
|---|---|---|
| Site ID | leer | Identifiziert diese Site gegenüber Ihrem ClickTrail-Konto |
| Endpoint-URL | leer | Wohin Payloads per POST gesendet werden |
| Consent-Resolver-Klasse | leer | Eigene `ConsentResolverInterface`-Implementierung, die den normalisierten Snapshot zurückgibt; leer = alle Signale „unknown" |
| Persistenz erfordert `analytics_storage` | an | Ohne erteilten Analytics-Consent nichts speichern |
| Click-ID-Speicherung erfordert `advertising_storage` | an | gclid/fbclid/... ohne Advertising-Consent aus der Speicherung entfernen |
| Gehashte Lead-Daten an Ad-Ziele senden (`ad_user_data`) | aus | Zusätzliche Schranke für das Weiterleiten gehashter Lead-Daten; erfordert weiterhin erteiltes `ad_user_data` |
| First-Party-Proxy | aus | ClickTrail-Loader von Ihrer eigenen Domain ausliefern |
| Formular-Übermittlungen mappen | an | `lead_created` bei Formular-Übermittlungen ausgeben |
| Benutzerregistrierungen mappen | an | `lead_created` bei Registrierung ausgeben |
| Commerce-Bestellungen mappen | an | `sale` beim Abschluss einer Bestellung ausgeben |
| Erstattungen mappen | an | `refund` ausgeben |

## Consent

ClickTrail ersetzt Ihre Consent-Plattform nicht; es gehorcht ihr. Der normalisierte Consent-Vertrag (Capabilities, Snapshot-Form, Verhaltensmatrix) liegt in [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Anbieter: Implementieren Sie `ClickTrail\Craft\Services\Consent\ConsentResolverInterface` (liefert den aktuellen `ClickTrail\Consent\ConsentSnapshot`) und verweisen Sie die Plugin-Einstellung darauf. Echte CMP-Adapter sind zurückgestellt; das WordPress-Plugin liest direkt die WP Consent API.
- Bei unbekanntem Consent: **nichts speichern oder senden**. Unterdrückte Aktionen werden mit `suppressionReason()` in der Diagnostik protokolliert.
- Der aufgelöste Snapshot wird neben dem Attributionszustand gespeichert und reist mit jedem Event (`consent`-Schlüssel in jedem Payload).

## Auslieferung

Payloads werden als JSON an `<endpoint>/events` gesendet. Fehlgeschlagene Auslieferungen werden als Warnung protokolliert, damit nichts geräuschlos verschwindet. Der vollständige Transport (Retries mit Backoff, Idempotency Keys) gehört zum gemeinsamen SDK-Client, sobald dessen Anbindung fertig ist.

## Wie es sich unterscheidet

| Übliches Analytics-Setup | ClickTrail für Craft |
|---|---|
| Sessions und Seiten im Dashboard | Kampagne, Keyword, Click-ID und Landingpage **an jeder Übermittlung, jedem Kunden und jeder Bestellung** |
| Client-seitige Tags in Eigenpflege | Eine Twig-Variable, ein First-Party-Loader |
| Attributionslogik pro Plattform dupliziert | Eine deterministische Engine, fixture-geprüft über WordPress, GTM und PHP-Integrationen |

## Tests

CI in GitHub Actions lintet bei jedem Push alle PHP-Dateien ([Workflow](https://github.com/vizuh/clicktrail-craft/blob/main/.github/workflows/ci.yml)).

## Lizenz

MIT; Copyright (c) 2026 Vizuh OÜ. Siehe [LICENSE](LICENSE).
