# Harlequin — Changelog

## 1.4.0 — 2026-04-30

### Changed
- Verbal rebrand to **Red-Headed Pro — WooCommerce Order Export** (final naming, Nicolas-validated).
- Suite renamed: **Ultimate Woo Powertools** *(by The Lion Frog)*.
- Triple version sync (header / readme Stable tag / manifest.json) at 1.4.0.

### Unchanged
- Slug `woo-order-pro`, option keys, REST routes, hooks, DB tables, text-domain — all UNCHANGED.
- No data migration. Backward-compatible with 1.3.0.

## 1.3.0 — 2026-04-30

### Changed
- Verbal rebrand to **Red-Headed Pro — WooCommerce Order Export** (poster-aligned: only species in the official Amazon Rainforest poster).
- Plugin Name header, admin H1, sub-menu label and readme synchronized.
- New mascot asset: `assets/img/mascot-bull-v1.svg` (placeholder, designer Figma incoming).
- Suite renamed to **Ultimate Woo Powertools** (was "The Lion Frog Suite").

### Unchanged
- Slug `woo-order-pro` UNCHANGED. Option keys, REST routes, hooks, DB tables, text-domain UNCHANGED.
- No data migration. Backward compatible with 1.2.1.

## 1.2.0 — 2026-04-30

🗂 **Profile editor v2 — column picker à la WC Customer/Order/Coupon Export.**

### New
- Two-pane column builder (catalog ↔ active list) with checkbox-grid + drag-drop reorder.
- 30+ pre-mapped order fields grouped by category (Order, Totals, Payment, Billing, Shipping).
- Editable header label per column (free-text, defaults to engine label).
- Custom meta column add: type a meta_key + label, get a `meta:<key>` column.
- Status filter is now a checkbox-chip grid (Pending / Processing / Completed / …) populated from `wc_get_order_statuses()`.
- Search input filters the catalog live.
- "Use defaults" / "Clear" buttons.

### Engine
- New `Pelican_Export_Engine::column_catalog()` + `column_groups()` (filter `pelican_column_catalog` for third-party extensions).
- New `normalize_columns()` ensures all builders receive the `{ key, label }` shape (legacy plain-string lists still accepted — backward compat).
- New `default_label_for( $key )` helper.

### Unchanged
- Profile data shape (still serialized to `columns` JSON); option keys, REST routes, hooks, DB tables — all preserved.

## 1.1.0 — 2026-04-30

🃏 **Verbal rebrand + slug rename** (Pélican → Harlequin · pelican-pro → woo-order-pro).

### Changed
- Plugin Name header: `Harlequin Pro — WooCommerce Order Export`.
- Slug renamed `pelican-pro` → `woo-order-pro` (function-descriptive, suite-wide consistency).
- Plugin folder + main bootstrap file renamed.
- Admin H1: now displays the harlequin mascot SVG + baseline.
- New asset: `assets/img/mascot-harlequin-v1.svg`.

### Unchanged
- Internal classes (`Pelican_*`), constants (`PELICAN_*`), REST routes (`/pelican/v1/*`), hooks (`pelican_*`), DB tables (`wp_pl_*`), text-domain (`pelican`).
- Option keys, encrypted credentials, profile data — all preserved.
- No data migration required.

### Rollback
- Replace plugin folder with archived `pelican-pro-v1.0.0.zip`. No DB rollback needed (all option keys/tables unchanged).

## 1.0.0 — 2026-04-30

🦩 **Initial release** — born as the 14th plugin of The Lion Frog suite (originally as Pélican).

### Lite + Pro
- HPOS-compatible order export pipeline (fetch → map → build → deliver → log)
- Bulk action on the WC orders list
- Lion Frog DNA admin (turquoise + orange + gold) with shared in-page nav
- Soft Lock matrix — Pro features visible & locked in Lite
- Hub registration via `the_froggy_hub_ecosystem` filter
- PolyLang + WPML compatibility

### Lite
- Format: CSV
- Destinations: Email (30/24h sliding cap) + SFTP
- 1 export profile, manual + bulk only

### Pro
- Formats: CSV, TSV, JSON, NDJSON, XML, XLSX
- Destinations: Email (unlimited), SFTP, Google Drive, REST endpoint, Local ZIP
- Cron schedules (hourly / twice-daily / daily / weekly)
- Auto-trigger on WC order status change
- HMAC-signed webhooks + REST API
- Unlimited profiles + multi-destination per profile
