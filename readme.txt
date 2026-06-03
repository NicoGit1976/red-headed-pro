=== Red-Headed Pro — Orders Export Manager ===
Contributors: thelionfrog
Tags: woocommerce, export, csv, xlsx, json, xml, sftp, cron, webhooks
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.6.0
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

* REST API endpoints (`/red-headed-pro/v1/profiles`, `/jobs`)
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

= 1.5.1 - 2026-05-29 =
* **Feature (G1):** Filename pattern now supports `{date:FORMAT}` dynamic placeholder — any PHP date format (e.g. `{date:d-m-Y-H-i-s}`). Filesystem-unsafe characters are auto-replaced with dashes.
* **Feature (G2):** Email attachment toggle — per-destination checkbox to send notification-only emails without the export file attached.
* **Feature (G3):** Full email configurability per destination — subject line with `{placeholders}`, from email, from name, CC, BCC. Global defaults in Settings → Destinations, per-destination overrides in profile.
* **Fix:** Dispatcher now injects profile context (name, format, job_id, records, first_order) into email destinations for placeholder resolution in subject lines.
* **Fix:** Settings → Destinations email defaults form now saves/restores from_email, from_name, CC, BCC correctly.
* **Polish:** Filename placeholder hint in profile editor updated with all 20 available tokens.

= 1.5.0 - 2026-05-27 =
* **Fix (B1):** Bulk action now loads the user's profile (format, columns, destinations, casts) instead of hardcoding CSV with default columns. Precedence: explicit `red_headed_profile_id` in request → first saved profile → ad-hoc CSV fallback.
* **Fix (B2):** Bulk action emoji changed from joker to frog mascot.
* **Fix (B3):** Empty columns safeguard — if column normalization produces an empty list, falls back to default columns so exports never produce headerless files.
* **Feature (F4):** Dynamic order meta discovery — "Scan order meta" button in the column picker queries the database (HPOS-safe) and lists all available meta keys for one-click addition.
* **Feature (P1):** Raw preview toggle — job preview modal now has a Raw/Table toggle with copy-to-clipboard, showing actual file content (JSON, CSV, XML, etc.).
* **Feature (P2):** Dry-run mode — build the export file without delivering to destinations. Available from the profile actions (🧪 button) and bulk action. Job status logged as `dry_run`.
* **Feature (P3):** Import / Export profile as JSON — export a profile configuration as a `.json` file, import it on another site or share with a colleague.
* **Feature (P4):** Webhook payload reference — Settings → Webhooks now shows sample payloads for all three events with header documentation and HMAC verification example.
* **HPOS (P5):** Audit and fixes — `post__in` → `include` in `wc_get_orders()`, dashboard "WC Orders" link uses HPOS-safe admin URL (`admin.php?page=wc-orders`). The single `get_post_meta()` call (SKU fallback on trashed products) is on the products table and is HPOS-compatible.
* **Already implemented (verified):** Field aliasing (F1) via column labels, format coercion (F2) via cast system, nested line items (F3) via `json_shape=nested`, bare JSON object (F5) via `json_bare` flag — all confirmed functional, no code changes needed.

= 1.4.50 - 2026-05-27 =
* **Fix:** Local folder destination defaults panel in Settings → Destinations.
* **Fix:** Draft/auto-draft statuses filtered from order status checkboxes and post-export dropdown.
* **Fix:** PHP_INT_MAX raw number display replaced with ∞ symbol.
* **Fix:** Text domain changed from `red-headed-pro` to `red-headed-pro` throughout.
* **Polish:** Select chevron CSS, drawer ARIA, focus trap, default retry/auto-trigger values.

= 1.4.44 - 2026-05-17 =
* **Docs homogenization** — readme.txt rewritten to reflect actual Lite/Pro feature parity verified against the Soft_Lock matrix.
* **Soft-Lock landing** — features list synchronized with the verified Lite/Pro split (manual + auto, 6 formats, 6 destinations, advanced filters, field mapper, computed columns, line-item mode, post-export status change, custom WC statuses, REST API, webhooks, multilingual).

= 1.4.5 =
* **Fix (CRITICAL):** License check used the LEGACY slug (`'woo-order-pro'`) but Hub stores under the NEW slug (`'red-headed-pro'`) — mismatch made FH_License_Manager::check_license() return 'missing' even with a valid license active in the Hub → soft-lock kicked in. Pro features were unreachable. Now uses the new slug consistently.

= 1.4.4 =
* **Fix (CRITICAL):** Removed obsolete legacy main file (`woo-order-pro.php`). Both `woo-order-pro.php` and `red-headed-pro.php` carried a `Plugin Name:` header → WordPress loaded them as TWO plugins → fatal "Cannot redeclare" on activation. Plugin was unactivatable since the rebrand.

= 1.1.0 — 2026-04-30 =
* Verbal rebrand: ships as **Red-Headed Pro — Orders Export Manager**.
* Slug renamed `red-headed-pro-pro` → `red-headed-pro` (final naming).
* Mascot: Red-Headed Poison Frog.
* Internal class names, REST routes, hooks, options, DB tables: UNCHANGED.
* Backward-compatible with v1.0.0 data — no migration required.

= 1.0.0 — 2026-04-30 =
* Initial release (as Pélican).
