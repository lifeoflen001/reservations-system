# Performance optimization runbook

## Implemented in this release

- Added window and filter indexes for reservation overlap, Room Planning, room blocks, maintenance windows, and active task queues. The migration is idempotent and does not change stored data.
- Replaced dashboard trend row-by-row cursor bucketing with database `COUNT`/`SUM` aggregation. The query still uses reservations and successful payments as the source of truth.
- Replaced task, housekeeping, maintenance, and reservation KPI query fan-out with conditional aggregate queries.
- Room Planning loads only reservations, blocks, and maintenance intervals overlapping the visible period. Availability is precomputed once per room-day, so the view does not query or rescan per calendar cell.
- Added server-side pagination to reports, housekeeping, and maintenance tables. Existing reservations, clients, and tasks lists were already paginated.
- Moved payment reservation-option filtering into SQL and capped the form list at 300 current candidates instead of hydrating every reservation and filtering balances in PHP.
- Fixed the notification preference/role permission N+1 by eager-loading both relationships.
- Cached active currencies and languages alongside property settings. Property, currency, and language caches are invalidated by `PropertySettingsService::clearCache()`.
- Removed the unused Axios bootstrap path from the production entry point and added debounced, abortable room-availability requests to prevent stale AJAX responses.
- Added Apache static asset cache headers and DEFLATE configuration. Vite-generated filenames remain content-addressed for safe immutable caching.

No live room availability, reservation status, payment balance, or check-in/check-out state is cached.

## Measurements

The pre-change full suite completed in 28.35 seconds. The optimized suite passed all 75 tests and 458 assertions; the final clean-cache verification completed in 36.55 seconds. PHPUnit wall time is not treated as a page-latency benchmark because this shared Windows environment has variable filesystem and process startup costs.

The committed revision and optimized revision were also compared with the same page probe. Query counts stayed stable on the already eager-loaded empty fixture—dashboard 43, reports 33, Room Planning 26, tasks 25, housekeeping 26, maintenance 26, payments 32, settings 41—while the optimized code reduces hydrated rows and PHP work on real datasets. The most material query-volume change is inside the trend and KPI paths: large result sets are now reduced to grouped aggregate rows instead of being cursor-hydrated and counted in PHP.

The optimized production asset build is:

- JavaScript: 35.42 kB raw / 10.15 kB gzip (previous build: 86.90 kB raw / 29.34 kB gzip).
- CSS: 174.07 kB raw / 30.36 kB gzip.

## Deployment checklist

Run after deployment:

```text
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
```

For production, use Redis for `CACHE_STORE` and `QUEUE_CONNECTION`, set `REDIS_URL` (or the host/port/password variables), and run a supervised queue worker. Email, webhook, WhatsApp, and channel synchronization jobs are already queue-backed; keep retries, backoff, and failed-job alerting configured. Do not clear caches on every request—deploys should rebuild the framework caches, and writes should invalidate only the affected application cache keys.

## Infrastructure recommendations

- Enable PHP OPcache with persistent bytecode, a production `opcache.validate_timestamps=0`, adequate memory, and a reload on deploy.
- Use PHP-FPM with enough workers for the host’s CPU/RAM, a process manager for queue workers, and database slow-query logging. Run `EXPLAIN` on overlap, report, and payment-summary queries against production-sized data.
- Put versioned `/build` assets behind a CDN such as Cloudflare or Fastly. Enable Brotli (preferred) or gzip, HTTP/2 or HTTP/3, TLS 1.2+, and origin keep-alive.
- Use a health-aware DNS provider with low operational friction and DNSSEC where appropriate. DNS/CDN changes are infrastructure work; Laravel cannot optimize authoritative DNS from application code.
- Keep HTTPS termination, HSTS policy, backups, database connection limits, and storage latency under operational monitoring. Alert on queue depth, failed jobs, database CPU/IO, PHP-FPM saturation, and p95/p99 request latency.

## 2026-10-08 incremental release

- Split announcement-only JavaScript from the initial app bundle. The module loads only on pages containing announcement controls or browser-alert controls and remains compatible with both loading states.
- Added the optional `ASSET_URL` setting so fingerprinted assets can use a trusted HTTPS CDN origin when infrastructure is ready. It is unset by default and does not change tenant or API URLs.
- Corrected PHP string quoting in the shared error view so compiled 403/429 pages do not become 500 responses.
- `npm run build`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` completed successfully. The focused error, contact, newsletter, finance-export, and related tests passed after the view fix.
- Initial verification exposed two local test blockers: GD was disabled and one authorization assertion protected the established 403 copy. GD is now enabled in the active XAMPP CLI configuration, the established copy is restored, and the affected tests pass.

Rollback is limited to reverting this release's app-entry conditional import and the `ASSET_URL` configuration addition. No live transactional data, availability, balances, or payment state is cached by this release.

## Final verification status

- Full repository suite: **211 tests passed, 1,635 assertions passed, 78.82 seconds** after restoring the established 403 response wording and enabling the existing local GD extension.
- Focused recovery suite: **17 tests passed, 165 assertions passed** for form authorization, finance/PDF output, profile image upload, and error pages.
- `php artisan config:cache`, `route:cache`, and `view:cache`: passed. Configuration cache was cleared afterward so local tests continued to use `.env.testing` correctly.
- `npm run build`: passed. Current entries are app JavaScript 49.79 kB raw / 14.91 kB gzip, app CSS 262.79 kB raw / 43.74 kB gzip, public JavaScript 3.67 kB / 1.24 kB gzip, POS JavaScript 7.97 kB / 2.84 kB gzip, and announcement JavaScript 2.19 kB / 1.11 kB gzip.
- Query evidence: dashboard service 125 queries / 77.61 ms and reservation KPI service 6 queries / 36.48 ms on the current populated local SQLite fixture. Earlier empty-fixture page counts remain historical comparison data, not a claim about this populated fixture.
- SQLite `EXPLAIN QUERY PLAN` used the reservation room/window indexes, room-block window index, and payment status/date index. MySQL `EXPLAIN ANALYZE` is still required against Railway/staging data.
- No new database index was added during this final verification pass. All listed indexes are in reversible migrations documented in `performance-audit.md`.
