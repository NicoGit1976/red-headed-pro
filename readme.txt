=== Red-Headed Pro — Exports Orders Everywhere, Anytime ===
Contributors: thelionfrog
Tags: woocommerce, export, csv, xlsx, json, xml, sftp, cron, webhooks
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.4.12
License: GPL-2.0-or-later

🐂 Exports WooCommerce orders everywhere, anytime — multi-format, multi-destination, scheduled & event-driven. Part of Ultimate Woo Powertools (by The Lion Frog).

== Description ==

**Red-Headed Pro** exports your WooCommerce orders everywhere, anytime — manual or automatic, single or bulk, sent to anywhere in the format the receiver expects.

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
2. Activate it. Red-Headed Lite (if installed) is auto-deactivated.
3. Go to **Froggy Hub → Red-Headed** to license + configure.

== Frequently Asked Questions ==

= Does it support HPOS? =
Yes. Custom Order Tables (HPOS) compatibility is declared at boot.

= How is SFTP password stored? =
AES-256-CBC encrypted at rest with `wp_salt('auth')`.

= Is there a free version? =
Yes — **Red-Headed Lite**: manual + bulk, CSV only, Email + SFTP. Pro features are visible & soft-locked.

== Changelog ==

= 1.4.5 =
* **Fix (CRITICAL):** License check used the LEGACY slug (`'woo-order-pro'`) but Hub stores under the NEW slug (`'red-headed-pro'`) — mismatch made FH_License_Manager::check_license() return 'missing' even with a valid license active in the Hub → soft-lock kicked in. Pro features were unreachable. Now uses the new slug consistently.

= 1.4.4 =
* **Fix (CRITICAL):** Removed obsolete legacy main file (`woo-order-pro.php`). Both `woo-order-pro.php` and `red-headed-pro.php` carried a `Plugin Name:` header → WordPress loaded them as TWO plugins → fatal "Cannot redeclare" on activation. Plugin was unactivatable since the rebrand.

= 1.1.0 — 2026-04-30 =
* Verbal rebrand: ships as **Harlequin Pro — WooCommerce Order Export**.
* Slug renamed `pelican-pro` → `woo-order-pro` (function-descriptive).
* Mascot: harlequin frog (Atelopus) — placeholder SVG asset.
* Internal class names, REST routes, hooks, options, DB tables: UNCHANGED.
* Backward-compatible with v1.0.0 data — no migration required.

= 1.0.0 — 2026-04-30 =
* Initial release (as Pélican).
