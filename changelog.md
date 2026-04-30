# Pélican — Changelog

## 1.0.0 — 2026-04-30

🦩 **Initial release** — born as the 14th plugin of The Lion Frog suite.

### Lite + Pro
- HPOS-compatible order export pipeline (fetch → map → build → deliver → log)
- Bulk action **🦩 Export with Pélican** on the WC orders list
- Lion Frog DNA admin (turquoise + orange + gold) with shared in-page nav
  (Dashboard / Exports / Settings)
- Soft Lock matrix — Pro features visible & locked in Lite
- Hub registration via `the_froggy_hub_ecosystem` filter
- PolyLang + WPML compatibility (translatable email subject + body)

### Lite
- Format: CSV
- Destinations: Email (30/24h sliding cap) + SFTP
- 1 export profile, manual + bulk only

### Pro
- Formats: CSV, TSV, JSON, NDJSON, XML, XLSX
- Destinations: Email (unlimited), SFTP, Google Drive, REST endpoint, Local ZIP
- Cron schedules (hourly / twice-daily / daily / weekly)
- Auto-trigger on WC order status change (fire-once dedupe + min-total threshold)
- HMAC-signed webhooks (`export.generated` / `.delivered` / `.failed`)
- REST API (`/pelican/v1/profiles` + `/jobs`)
- Unlimited profiles + multi-destination per profile
