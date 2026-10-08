# Lodgix scalability and production-readiness notes

## Current application shape

The application is tenant-aware and keeps the database as the source of truth for reservations, room availability, payments, balances, and check-in/check-out state. Do not introduce a cache that can make those values disappear or become stale. Safe cache candidates remain property/settings catalogs, active currencies and languages, reservation sources, and explicitly historical or read-only report summaries.

The local environment uses database-backed sessions, cache, and queues. That is suitable for development and a single instance, but a horizontally scaled deployment should use shared infrastructure:

- Redis for cache, sessions, and queue transport.
- One supervised queue worker process per service capacity unit, with retry and failed-job alerting.
- One scheduler instance, or a scheduler with a distributed lock.
- MySQL with slow-query logging, backups, connection limits, and indexes verified against production-shaped data.
- Durable object storage for user uploads and other files; local disk must not be the only copy behind multiple instances.

## Load balancing and deployments

Before adding a second web instance, verify that sessions and cache are shared, `APP_KEY` is identical, trusted proxies and HTTPS forwarding are configured, and no request depends on local mutable disk. Run migrations as a single release step, then warm config, route, and view caches. Queue workers must be restarted gracefully after a release so they run the current code.

Use `/up` and `/api/health` for platform health checks. Health checks should verify the intended readiness dependencies without exposing secrets. Monitor p95/p99 latency, database CPU/IO, connection saturation, queue depth, failed jobs, storage failures, and PHP-FPM worker saturation.

## CDN, browser caching, and network edge

Vite fingerprinted files and the existing Apache configuration are ready for long-lived immutable browser caching. Set `ASSET_URL` to the trusted HTTPS CDN origin when a CDN is provisioned. Do not cache HTML, authenticated responses, reservation availability, payments, balances, or tenant-specific responses at a shared edge. Enable Brotli where available, gzip as fallback, HTTP/2 or HTTP/3, TLS, origin keep-alive, and a clear purge strategy for non-fingerprinted files.

DNS, CDN, TLS termination, HTTP protocol selection, and authoritative DNS health routing are infrastructure concerns; Laravel code cannot optimize them directly. Use a health-aware DNS provider, DNSSEC where appropriate, and documented rollback records.

## Redis migration checklist

1. Provision Redis with persistence/availability appropriate to the environment.
2. Set `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, and `QUEUE_CONNECTION=redis` only after connectivity and eviction policy are verified.
3. Set a unique queue name per environment and run a supervised worker.
4. Confirm cache keys include organization/property context where data is tenant-scoped.
5. Confirm write paths invalidate affected keys immediately; never clear all caches on every request.
6. Exercise queue retries, worker restarts, Redis failover, and failed-job recovery in staging.

## Platform control plane

The separate Platform UI is not isolated from SaaS. Platform services and routes manage organizations, properties, plans, subscriptions, entitlements, customer administration, support sessions, and audits. It is therefore retained. A future removal would need a signed product decision, route/model/service dependency mapping, tenant migration, authorization review, and rollback plan.

## Verified local versus deployment-ready

Verified locally:

- Laravel config, route, and view caches compile.
- Hashed Vite manifest entries resolve through `ASSET_URL`, including the CSS/JS entries used by layouts.
- Tenant and SaaS isolation tests pass.
- Queue jobs are implemented in application code.
- Health endpoints and static Apache cache/DEFLATE rules exist.

Not enabled or not verifiable on this workstation:

- Redis is not the local cache/session/queue driver.
- No external CDN is provisioned.
- `artisan serve` does not execute Apache Brotli/DEFLATE or immutable-header rules.
- No MySQL staging connection was available for `EXPLAIN ANALYZE` or slow-query logs.
- No multi-instance load balancer, shared object storage, or managed connection pool was changed.
- Lighthouse CLI is not installed locally; no Lighthouse score or Core Web Vitals value is claimed.

The exact production verification sequence is: provision a disposable Railway/staging environment, apply migrations, build versioned assets, set `ASSET_URL` only after CDN sync, run `config:cache`, `route:cache`, and `view:cache`, verify response headers and CDN paths, switch shared Redis only after connectivity tests, run one scheduler and supervised queue workers, then run authenticated Lighthouse and k6/Artillery tests against representative data. Keep reservation availability, payment balances, and tenant-specific responses out of shared CDN caches.
