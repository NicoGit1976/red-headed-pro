=== Red-Headed Pro — Orders Export Manager ===
Contributors: thelionfrog
Tags: woocommerce, export, csv, xlsx, json, xml, sftp, cron, webhooks
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.4.44
License: GPL-2.0-or-later

Exports WooCommerce orders everywhere, anytime — multi-format, multi-destination, scheduled and event-driven, with a visual field mapper, computed columns, REST API and HMAC-signed webhooks. Part of Ultimate Woo Powertools (by The Lion Frog).

== Description ==

**Red-Headed Pro** turns WooCommerce order export into a full automation layer. Run any combination of formats, deliver to any combination of destinations, schedule it on cron or trigger it on WC order status changes, fan out a single export to multiple endpoints, map columns visually, compute custom fields, and ship the result to your warehouse / 3PL / accountant / CRM / data lake in the shape they expect.

= Features =

**Formats (6 total)**

* CSV
* TSV
* JSON
* NDJSON
* XML (DOM-built, escaped)
* XLSX (PhpSpreadsheet when available, safe fallback)

**Destinations (6 total)**

* Email (multi-recipient, attachment, unlimited sends)
* SFTP (phpseclib3 + ssh2 fallback, AES-256-CBC at-rest password)
* Google Drive (OAuth)
* REST endpoint (Bearer / Basic / custom header)
* Local ZIP archive
* Direct download (browser stream)

**Triggers**

* Manual (one-shot or bulk action)
* Cron schedules (hourly / twice-daily / daily / weekly / custom interval)
* Auto-trigger on WC order status change (with `fire_once` dedupe + min-total threshold)

**Profiles & routing**

* Unlimited export profiles
* Multi-destinations per profile (simultaneous fan-out)
* Each profile owns its filters, columns, destinations, schedule

**Advanced filters**

* Date range
* Payment method
* Shipping method
* Order status (including custom WC statuses)
* Product category
* SKU
* Customer role
* Minimum / maximum order amount

**Field mapper & transforms**

* Visual drag-and-drop column picker
* Rename headers, reorder, hide
* Per-column transforms
* Computed columns (formulas across order fields)
* Static columns (fixed value injected on every row)

**Export modes**

* One row per order (default)
* Line-item export mode (one row per product within the order)

**Post-export automation**

* Auto-set order status after a successful export (e.g. "processing" → "completed")
* Register custom WC status `wc-rh-exported` to mark already-exported orders

**Integrations**

* REST API endpoints (`/pelican/v1/profiles`, `/jobs`)
* HMAC-signed webhooks (`export.generated`, `export.delivered`, `export.failed`, SHA-256, retry ×3 exponential)
* PolyLang & WPML compatible (translatable email subject + body)

**Platform**

* HPOS (Custom Order Tables) compatible
* GDPR-friendly defaults
* The Lion Frog Hub integration — license, soft-lock, single admin tree
* Admin dashboard with KPIs, recent jobs, format coverage

== Installation ==

1. Upload `red-headed-pro` to `/wp-content/plugins/`.
2. Activate it. Red-Headed Lite (if installed) is auto-deactivated, all data preserved.
3. Go to **Froggy Hub → Red-Headed** to license + configure.

== Frequently Asked Questions ==

= Does it support HPOS? =
Yes. Custom Order Tables (HPOS) compatibility is declared at boot.

= How is SFTP password stored? =
AES-256-CBC encrypted at rest with `wp_salt('auth')`.

= Is there a free version? =
Yes — **Red-Headed Lite**: manual + bulk, CSV only, Email + SFTP + direct download, 1 profile. Pro features are visible & soft-locked inside Lite.

== Changelog ==

= 1.4.44 - 2026-05-17 =
* **Docs homogenization** — readme.txt rewritten to reflect actual Lite/Pro feature parity verified against the Soft_Lock matrix.
* **Soft-Lock landing** — features list synchronized with the verified Lite/Pro split (manual + auto, 6 formats, 6 destinations, advanced filters, field mapper, computed columns, line-item mode, post-export status change, custom WC statuses, REST API, webhooks, multilingual).

= 1.4.5 =
* **Fix (CRITICAL):** License check used the LEGACY slug (`'woo-order-pro'`) but Hub stores under the NEW slug (`'red-headed-pro'`) — mismatch made FH_License_Manager::check_license() return 'missing' even with a valid license active in the Hub → soft-lock kicked in. Pro features were unreachable. Now uses the new slug consistently.

= 1.4.4 =
* **Fix (CRITICAL):** Removed obsolete legacy main file (`woo-order-pro.php`). Both `woo-order-pro.php` and `red-headed-pro.php` carried a `Plugin Name:` header → WordPress loaded them as TWO plugins → fatal "Cannot redeclare" on activation. Plugin was unactivatable since the rebrand.

= 1.1.0 — 2026-04-30 =
* Verbal rebrand: ships as **Red-Headed Pro — Orders Export Manager**.
* Slug renamed `pelican-pro` → `red-headed-pro` (final naming).
* Mascot: Red-Headed Poison Frog.
* Internal class names, REST routes, hooks, options, DB tables: UNCHANGED.
* Backward-compatible with v1.0.0 data — no migration required.

= 1.0.0 — 2026-04-30 =
* Initial release (as Pélican).
