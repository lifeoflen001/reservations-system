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

## Completion and gap matrix

The statuses below are based on source inspection, local measurements, EXPLAIN output, and tests. Documentation or an example environment variable is not treated as proof that infrastructure is active.

| Requirement | Status | Files involved | Evidence | Verification method | Remaining work / deployment dependency |
| --- | --- | --- | --- | --- | --- |
| Production JavaScript build, minification, and tree shaking | Implemented and verified | `package.json`, `vite.config.js`, `resources/js/app.js`, `public/build/manifest.json` | Vite emits hashed/minified chunks and a dynamic announcement chunk | `npm run build` passed; manifest inspected | Rebuild in CI/deploy; no extra frontend dependency is required |
| Route-level JavaScript splitting and noncritical deferral | Implemented and verified | `resources/js/app.js`, `resources/js/announcements.js`, `resources/js/pos.js` | Announcement and POS modules are dynamic imports gated by page selectors | Manifest shows `announcements-*.js` and `pos-*.js` as dynamic entries; focused announcement tests passed | Measure browser transfer/execution on authenticated pages with Lighthouse when tooling is available |
| CSS minification and Tailwind production compilation | Implemented but unverified for optimality | `resources/css/*.css`, `vite.config.js` | Vite emits a minified hashed CSS entry | Production build passed | Current CSS is 262.79 kB raw / 43.74 kB gzip, up from the prior 242.70 kB / 40.63 kB; perform a selector-coverage audit before removing CSS |
| Image optimization and responsive image delivery | Partially implemented | `public/assets/branding/mbvl.webp`, `resources/views/layouts/*.blade.php`, public screenshot inventory | WebP and hashed image assets are present; the largest audited image is 578.92 kB | Manifest and filesystem sizes inspected | Generate measured responsive variants and optimize the 578.92 kB image only after visual comparison; CDN/origin image policy remains deployment work |
| Redundant libraries and Axios bootstrap | Implemented and verified | `resources/js/app.js`, `resources/js/bootstrap.js`, `package.json` | `app.js` does not import the legacy Axios bootstrap; no Axios package is in the production dependency graph | Import/dependency search and bundle inspection | The unused legacy file can be removed in a separate cleanup, but it has no current bundle impact and was not deleted in this pass |
| Search debouncing and stale request cancellation | Implemented and verified | `resources/js/ui.js`, search/availability controllers and views | Search and room availability interactions use debounced/cancelable requests | Source inspection and existing UI/feature tests | Browser waterfall verification remains a Lighthouse/DevTools task |
| Server-side pagination for large tables | Implemented and verified | `app/Http/Controllers/*`, `app/Support/TablePagination.php`, relevant Blade tables | Reservations, clients, rooms, tasks, reports, housekeeping, maintenance, finance, platform lists use pagination or bounded results | `PaginationTest`, module tests, and controller inspection passed | Confirm per-tenant row limits against production data |
| Dashboard and report aggregation | Implemented and verified | `app/Services/HotelAnalyticsService.php`, `app/Services/MiniDashboardMetricsService.php`, `app/Http/Controllers/ReportsController.php` | KPI/trend paths use `COUNT`, `SUM`, conditional aggregates, and bounded queries | Analytics, mini-dashboard, and reports tests passed; current dashboard service probe: 125 queries / 77.61 ms on the local populated SQLite fixture | Profile against representative MySQL data; payment recognition remains correctness-sensitive |
| Room Planning visible-range loading | Implemented and verified | `app/Http/Controllers/RoomPlanningController.php`, `app/Services/RoomAvailabilityService.php`, migrations `2026_09_20_000002_*` and tenant indexes | Reservations, blocks, and maintenance are filtered to overlap the visible period; availability is computed once per room/day, not per cell | Room Planning feature tests passed; SQLite EXPLAIN uses reservation window/status indexes | Run MySQL EXPLAIN with hundreds of rooms and representative date ranges |
| N+1 fixes in notifications and major lists | Implemented but unverified repository-wide | `app/Services/HotelNotificationService.php`, staff/client/finance/room controllers | Notification recipients eager-load `notificationPreferences` and `role.permissions`; list pages eager-load displayed relationships | Source inspection and full suite passed | Run a production-shaped query profiler to prove no remaining view-loop N+1 across all modules |
| Database indexes for analytics and operational windows | Implemented and verified locally | `database/migrations/2026_09_14_000270_add_analytics_indexes.php`, `2026_09_20_000001_add_mini_dashboard_metric_indexes.php`, `2026_09_20_000002_add_runtime_performance_indexes.php` | Indexes cover reservation windows/status/created dates, Room Planning filters/windows, task queues, payment status/date, and mini-dashboard metrics | Migrations are present and idempotent where applicable; SQLite EXPLAIN confirmed index use | Run MySQL `EXPLAIN` and compare cardinality/latency before considering any additional index |
| Tenant-scoped indexes and isolation | Implemented and verified | `database/migrations/2026_10_02_000010_*` through `000014_*`, tenant ownership traits/services | Organization/property prefixes are present on operational, finance, POS, task, integration, and API indexes | SaaS isolation/ownership tests passed | Run production MySQL index-size and write-cost review; no speculative indexes added |
| Query profiling and EXPLAIN | Partially implemented | `app/Services/*`, relevant controllers, database migrations | Current local query listener captured dashboard and KPI counts; SQLite plans were captured for reservation, block, and payment queries | `DB::listen` and `EXPLAIN QUERY PLAN` probes ran successfully | MySQL slow-query log and `EXPLAIN ANALYZE` require the deployed/staging database |
| Safe reference-data caching | Implemented and verified | `app/Services/PropertySettingsService.php`, `app/Services/SystemSettingsService.php`, `app/Services/PublicWebsiteContentService.php`, `app/Services/EntitlementService.php` | Settings, currencies, languages, public CMS data, and entitlements use scoped keys/TTL or forever caches | Cache invalidation code and SaaS cache-isolation tests passed | Use Redis/shared cache in multi-instance production |
| Cache invalidation | Implemented and verified | `PropertySettingsService`, `SystemSettingsService`, `PublicWebsiteContentService`, `EntitlementService`, settings/website controllers | Affected keys are explicitly forgotten on writes; no request-wide cache flush was added | Source inspection and settings/SaaS tests passed | Add operational alerts for cache backend failures; validate Redis eviction policy |
| Live reservation, availability, balance, and payment freshness | Implemented and verified | `RoomAvailabilityService.php`, `FinancialService.php`, reservation/payment controllers | Live operational values query the database; no aggressive cache was added | Reservation, payment, finance, and SaaS tests passed | None in application code; maintain DB consistency in deployment |
| Queueing slow email/API/webhook/channel work | Implemented but unverified in production | `app/Jobs/*`, `app/Services/HotelEmailService.php`, `WebhookService.php`, `ChannelSyncService.php` | Email, webhook, channel synchronization, and related jobs implement `ShouldQueue` | Source inspection and integration tests passed | A supervised worker, retries, failure alerts, and queue metrics must be configured on Railway |
| Static browser caching and compression | Implemented but unverified | `public/.htaccess` | Immutable one-year headers for versioned assets and DEFLATE rules for eligible types are present | Local Apache rules inspected; `artisan serve` correctly does not apply them | Verify on the actual Railway reverse proxy; enable Brotli if supported without double compression |
| CDN asset origin | Implemented and verified in application; blocked by infrastructure for activation | `config/app.php`, `.env.example`, Vite manifest, layouts | `asset()` and `@vite` generated correct `https://cdn.example.test/build/assets/...` URLs from the real manifest; public paths remain `/assets/...` | Tinker URL-generation probe passed | Provision CDN, upload/sync `public/build` and required public assets, set `ASSET_URL`, configure HTTPS/purge rules, and verify fonts/images |
| Redis readiness | Blocked by infrastructure | `.env.example`, `config/cache.php`, `config/queue.php`, `config/session.php`, `HOSTING.md` | Local runtime uses database cache/session/queue; Redis PHP extension is not loaded | `php artisan about` and PHP module inspection | Provision shared Redis and run worker/failover tests before switching production drivers |
| Connection pooling | Not implemented, with justification | Laravel database config, Railway hosting documentation | The app uses ordinary PDO connections; no external pooler is configured | Runtime/config inspection | Only add a managed pooler/proxy if MySQL connection saturation is measured in staging/production |
| Load balancing | Blocked by infrastructure | session/cache/storage/queue config, `docs/scalability.md`, `HOSTING.md` | No multi-instance infrastructure was changed; health endpoints exist | Architecture inspection and health tests passed | Shared sessions/cache, durable storage, trusted proxies, one scheduler, supervised workers, and rollout coordination are required |
| Lighthouse mobile/desktop metrics | Blocked by tooling | `docs/performance-audit.md` | `lighthouse` is not installed and `npx --no-install lighthouse` reported the package is unavailable locally | Tool availability check ran; no metrics were fabricated | Run the documented command in CI or a machine with Lighthouse/Chrome; capture public and authenticated pages |
| Controlled load testing | Implemented as local smoke only; production capacity unverified | local HTTP runtime only | 20 concurrent `/login` requests returned 20/20 successes in 3.75 s (~5.34 req/s) on single-threaded `artisan serve` | Node fetch probe ran locally | Use k6/Artillery against an isolated staging environment with MySQL/Redis and collect CPU, memory, DB, p50/p95/p99, and errors |
| Platform UI separation | Not applicable for deletion; refactoring plan proposed | `routes/web.php`, `app/Http/Controllers/Platform/*`, `app/Services/Platform/*`, `resources/views/platform/*`, SaaS services/models | Platform routes expose UI, while services enforce subscriptions, plans, entitlements, support context, tenant administration, 2FA, and audit | Route/service/view inventory and Platform/SaaS tests passed | Product approval is required before consolidating presentation routes. Preserve services, records, authorization, billing, and support access; see the plan below |

## Index inventory from the performance pass

The performance-specific indexes are reversible migrations; no new index was added in this final verification pass.

- `2026_09_14_000270_add_analytics_indexes.php`: `reservations_created_at_analytics_index`; `housekeeping_status_due_analytics_index`; `maintenance_status_due_analytics_index`.
- `2026_09_20_000001_add_mini_dashboard_metric_indexes.php`: `users_last_login_at_index`; housekeeping `(status, completed_at)`; maintenance `(status, priority, due_at)`; payments `(status, transaction_date)`; clients `created_at`.
- `2026_09_20_000002_add_runtime_performance_indexes.php`: reservations `(check_in, check_out)`; rooms planning filters; `room_blocks_window_index`; `maintenance_window_index`; `tasks_active_status_due_index`.
- Tenant ownership migrations add organization/property-leading indexes for reservations, rooms, blocks, maintenance, payments, finance, POS, tasks, integrations, APIs, announcements, and audit logs. These are isolation/scalability indexes, not speculative query tuning.

SQLite `EXPLAIN QUERY PLAN` confirmed index use for reservation room overlap, visible-range reservation lookup, room-block overlap, and payment lookup. MySQL execution plans remain a deployment-dependent verification item.

## Platform separation proposal — approval required

Do not delete the Platform module as part of performance work. The presentation-only layer is the `/platform` route group, its Blade views, navigation, and controller composition. The required operational layer includes `PlatformSubscriptionService`, `EntitlementService`, organization/property services, platform authentication/2FA, support access/context, audit logging, plan/feature records, subscription records, and tenant ownership checks.

A safe future refactor would consolidate the Platform UI behind a smaller internal operations surface, preserve the existing services and route authorization, migrate or redirect any needed workflows, run the Platform/SaaS isolation suite, and retain a rollback path. It requires a product decision about which platform administrators need which screens; no code removal is authorized by this audit.

## Final local evidence

| Measurement | Earlier local baseline | Current local verification | Interpretation |
| --- | ---: | ---: | --- |
| `/` average over 5 requests | 952.7 ms | 222.1 ms average over 10 requests; 362.6 ms p95 | Different runs and local server states; not a causal A/B claim |
| `/login` average over 5 requests | 312.9 ms | 183.5 ms average over 10 requests; 200.5 ms p95 | Development-server measurement only |
| `/api/health` average over 5 requests | 189.0 ms | 158.7 ms average over 10 requests; 193.3 ms p95 | Development-server measurement only |
| Dashboard service query count/time | Historical empty-fixture count 43 | 125 queries / 77.61 ms on current populated SQLite fixture | Dataset and code path differ; use MySQL profiling for release decisions |
| Reservation KPI service query count/time | Historical empty-fixture page count was not isolated | 6 queries / 36.48 ms on current SQLite fixture | Service-level probe, not full HTTP page count |
| Local concurrent smoke | Not run | 20/20 `/login` responses succeeded in 3.75 s; ~5.34 req/s | `artisan serve` is single-threaded; no capacity claim |
| Main JS bundle | 51.78 kB / 15.67 kB gzip | 49.79 kB / 14.91 kB gzip | Verified reduction from announcement code splitting |
| App CSS bundle | 242.70 kB / 40.63 kB gzip | 262.79 kB / 43.74 kB gzip | Current CSS is larger; further selector audit is still open |

## Exact local test environment fix

The local PHP runtime is XAMPP PHP 8.2.12. GD was available at `C:\xampp\php\ext\php_gd.dll` but disabled in `C:\xampp\php\php.ini`. The local configuration now has `extension=gd` enabled. A new PHP process reports `gd`, and the image/PDF tests pass. On another machine, enable the matching GD extension in the active `php.ini`, restart PHP-FPM/Apache or the CLI process, and confirm with `php -m | findstr /I gd`. This is a local machine setting and is not a repository change.
