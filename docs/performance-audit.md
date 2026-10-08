# Lodgix performance audit

Audit date: 2026-10-08

This audit was performed against the local working tree on Windows. It is a development baseline, not a production SLA measurement. No production deployment or live data load test was run.

## Runtime inventory

- Laravel 12.69.2
- PHP 8.2.12
- Composer 2.10.2
- Vite 7.3.6
- Blade views with vanilla JavaScript and Vite assets
- Local database: SQLite
- Production target: MySQL on Railway, with Redis recommended for shared cache, sessions, and queues
- Local timezone: Africa/Dar_es_Salaam

The application already has tenant-aware cache keys, indexed operational tables, database aggregates for dashboard/report metrics, visible-range Room Planning queries, server-side pagination, queue-backed external work, and immutable Apache headers for versioned assets. Those safeguards were retained.

## Baseline measurements

Five sequential local requests were sampled before this release. Times include the local development server and filesystem startup cost:

| Route | Status | Average | Minimum | Maximum |
| --- | ---: | ---: | ---: | ---: |
| `/` | 200 | 952.7 ms | 208.3 ms | 3527.1 ms |
| `/login` | 200 | 312.9 ms | 188.0 ms | 747.5 ms |
| `/api/health` | 200 | 189.0 ms | 173.8 ms | 224.3 ms |

These values should not be compared with production p95 until the app is measured behind Railway using a fixed region, warmed workers, representative MySQL data, and a repeatable authenticated browser profile.

The immediately prior production build measured 51.78 kB raw / 15.67 kB gzip for the main JavaScript bundle and 242.70 kB raw / 40.63 kB gzip for the application CSS. The current build is 49.79 kB raw / 14.91 kB gzip for the main JavaScript bundle and 262.79 kB raw / 43.74 kB gzip for application CSS. The CSS change is a generated-build variation and was not expanded in this release; CSS cleanup remains a follow-up measurement task.

The existing empty-fixture page probe recorded these query counts before the current release: dashboard 43, reports 33, Room Planning 26, tasks 25, housekeeping 26, maintenance 26, payments 32, settings 41. This release does not claim a backend query-count reduction; its measurable code change is initial JavaScript payload reduction through conditional announcement loading.

## Findings and actions

1. Announcement editing, rich-command, and browser-alert behavior was statically loaded into every app page. It is now imported only when one of its page selectors exists. The announcement module remains safe when loaded after `DOMContentLoaded`.
2. `ASSET_URL` is now exposed through `config/app.php` and `.env.example`, allowing a trusted HTTPS CDN origin for fingerprinted assets without changing application routes or tenant data paths.
3. Existing static asset caching, compression, route/config/view cache support, database aggregates, overlap filtering, eager loading, pagination, and queue boundaries were verified and preserved.
4. The shared error view contained unescaped apostrophes inside PHP single-quoted strings. That made expected 403/429 responses render as 500s after view compilation. The strings were corrected and the error-page tests pass.
5. The Platform control plane was not removed. It is coupled to plans, subscriptions, entitlements, tenant administration, support access, and SaaS billing; removing it safely requires a product decision and a dependency-by-dependency migration plan.

## Measurement gaps

Lighthouse was not available in the local toolchain, and no authenticated browser trace was fabricated. Run Lighthouse against warmed local and deployed URLs before setting a performance budget. Run a controlled load test against a disposable environment with production-shaped data; never use destructive test commands against a shared database.

Useful follow-up commands:

```text
npx lighthouse http://127.0.0.1:8000/login --output=html --output-path=storage/app/performance/lighthouse-login.html
php artisan about
php artisan optimize
```

For authenticated pages, capture a controlled session and record TTFB, response bytes, query count, p50/p95/p99 latency, slow-query samples, and queue latency. Compare dashboard, Room Planning, reports, tasks, and search separately.
