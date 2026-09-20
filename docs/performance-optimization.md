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
