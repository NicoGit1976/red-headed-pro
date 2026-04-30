# Harlequin — Changelog

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
