=== Harlequin Pro — WooCommerce Order Export ===
Contributors: thelionfrog
Tags: woocommerce, export, csv, xlsx, json, xml, sftp, cron, webhooks
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPL-2.0-or-later

🃏 Premium WooCommerce order export — multi-format, multi-destination, scheduled & event-driven. Part of The Lion Frog suite.

== Description ==

**Harlequin Pro** is the full-power order exporter for WooCommerce. Manual or automatic, single or bulk, send orders to anywhere in the format the receiver expects.

= Formats =
* CSV / TSV (with locale-aware decimals)
* JSON / NDJSON
* XML (DOM-built, escaped)
* XLSX (with PhpSpreadsheet when available, with safe fallback)

= Destinations =
* Email (multi-recipient, attachment)
* SFTP (phpseclib3 + ssh2 fallback)
* Google Drive (OAuth)
* REST endpoint (Bearer / Basic / custom header)
* Local ZIP archive
* Direct download

= Triggers =
* Manual (one-shot or bulk action)
* Cron schedules (hourly / twice-daily / daily / weekly / custom)
* Auto-trigger on WC order status change (with `fire_once` dedupe + min-total threshold)

= More =
* Multiple export profiles, each with filters / columns / destinations / schedule
* HMAC-signed webhooks (`export.generated`, `export.delivered`, `export.failed`)
* REST API (`/pelican/v1/profiles`, `/jobs`)
* PolyLang & WPML compatible (translatable email subject + body)
* Admin dashboard with KPIs, recent jobs, formats coverage
* The Lion Frog Hub integration — license, soft-lock, single admin tree

== Installation ==

1. Upload `woo-order-pro` to `/wp-content/plugins/`.
2. Activate it. Harlequin Lite (if installed) is auto-deactivated.
3. Go to **Froggy Hub → Harlequin** to license + configure.

== Frequently Asked Questions ==

= Does it support HPOS? =
Yes. Custom Order Tables (HPOS) compatibility is declared at boot.

= How is SFTP password stored? =
AES-256-CBC encrypted at rest with `wp_salt('auth')`.

= Is there a free version? =
Yes — **Harlequin Lite**: manual + bulk, CSV only, Email + SFTP. Pro features are visible & soft-locked.

== Changelog ==

= 1.1.0 — 2026-04-30 =
* Verbal rebrand: ships as **Harlequin Pro — WooCommerce Order Export**.
* Slug renamed `pelican-pro` → `woo-order-pro` (function-descriptive).
* Mascot: harlequin frog (Atelopus) — placeholder SVG asset.
* Internal class names, REST routes, hooks, options, DB tables: UNCHANGED.
* Backward-compatible with v1.0.0 data — no migration required.

= 1.0.0 — 2026-04-30 =
* Initial release (as Pélican).
