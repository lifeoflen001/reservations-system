# SAAS-05 isolation validation

This document records the Phase SAAS-05 tenant-isolation validation. It is a
security acceptance record, not permission to start SAAS-06 or to onboard
customer tenants automatically.

## Isolation matrix

| Surface | Organization/property boundary | Result |
| --- | --- | --- |
| Normal Eloquent lists and aggregates | Global tenant ownership scope on owned models | PASS in SQLite feature tests |
| Route model binding | Bound models resolve through the active tenant scope; foreign objects return 404 | PASS in `SaasIsolationTest` |
| Policies and super administrator | Gate resource pre-check rejects foreign owned models before the hotel super-admin bypass | PASS in `SaasIsolationTest` |
| Create/update/delete services | Server-side ownership assignment plus Reservation/POS/Finance/Task assertions | PASS in SAAS-04 and SAAS-05 focused tests |
| Request validation | Tenant-aware `exists`/`unique` rules on reservation, payment, task, housekeeping, maintenance, finance and POS paths | PASS in focused request/API coverage |
| Search and exports | Eloquent query scope and scoped transfer/export services | PASS for operational client export and search review |
| Files and attachments | Access remains through tenant-scoped parent bindings and local paths | PASS by parent-bound controller review; direct storage paths remain out of scope |
| Cache | Organization/property namespaced integration and property settings keys | PASS in `SaasTenantContextTest` and `SaasIsolationTest` |
| Jobs/events/scheduler | Worker activation for integration jobs; property iteration for scheduled announcements; notification ownership listener | PASS by focused code review and tests; queue execution should be rechecked in staging |
| API tokens and API resources | Token carries org/property and middleware activates it before controllers; resource bindings remain scoped | PASS in API expansion and isolation tests |
| Public website/CMS/auth | Deliberately platform-global and excluded from hotel tenant middleware | PASS; no operational data is queried by public routes |

## SQLite validation

The following commands are the repeatable isolated test run. SQLite uses an
in-memory database only:

```text
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/SaasFoundationTest.php tests/Feature/SaasTenantContextTest.php tests/Feature/SaasOperationalOwnershipTest.php tests/Feature/SaasIsolationTest.php tests/Feature/ApiV1ExpansionTest.php
```

Recorded result: **passed** — the complete SQLite suite reported 169 tests and
1,385 assertions with zero failures. The dedicated hardening matrix reported
4 tests and 86 assertions, also with zero failures.

## Disposable MariaDB validation

This must be run against a new, exact disposable database name and removed
afterwards. Never point this command at the operational `reservations_db`:

```text
DB_DATABASE=reservations_pos_integration_saas051_20261002 POS_MYSQL_INTEGRATION=true APP_ENV=testing DB_CONNECTION=mysql php artisan migrate --force
DB_DATABASE=reservations_pos_integration_saas051_20261002 POS_MYSQL_INTEGRATION=true APP_ENV=testing DB_CONNECTION=mysql php artisan test tests/Feature/SaasFoundationTest.php tests/Feature/SaasTenantContextTest.php tests/Feature/SaasOperationalOwnershipTest.php tests/Feature/SaasIsolationTest.php tests/Feature/SaasHardeningTest.php tests/Feature/ApiV1ExpansionTest.php tests/Feature/Phase09IntegrationsTest.php tests/Feature/OperationalDataTransferTest.php
```

The disposable schema was created with normal `migrate --force`; destructive
reset commands remain blocked for operational databases by the application
safety guard.

Recorded result: **passed** — MariaDB 10.4.32, all 21 migrations completed,
seed and validation passed, two operational backfills plus a dry-run completed
with no anomalies, and 39 focused tenant/integration/API/import tests passed
with 286 assertions. The reconciliation reported zero NULL property owners,
zero organizations without properties, and unchanged zero-valued financial
totals before/after the idempotent runs. The exact disposable database
`reservations_pos_integration_saas051_20261002` was dropped and verified absent
from `INFORMATION_SCHEMA`.

## SAAS-05.1 hardening update

The mandatory ownership hardening migrations 000015–000021 are now present.
They classify organization-wide nullable-property records explicitly, reject
NULL preconditions before tightening schema, and use restrictive foreign keys.
Runtime creation no longer falls back to the first property or organization;
trusted HTTP, API, job, import and webhook paths establish context explicitly.
The dedicated hardening test matrix is in
`tests/Feature/SaasHardeningTest.php`.

The disposable MariaDB migration, backfill, idempotency and financial
reconciliation gate is complete for this phase. A future staging run must use
the same exact-database procedure against a new disposable name.

## Known issues and boundaries

- Organization-wide audit records retain explicitly documented nullable
  `property_id` columns. Mandatory operational and organization ownership
  columns are hardened by SAAS-05.1 only after NULL preconditions pass.
- Global scopes are intentionally inert without a resolved tenant. This is
  required for migrations, backfills, setup, health and public/global flows;
  those code paths must not be used to serve tenant-authenticated data.
- Global number sequences remain globally unique and are not tenant-scoped.
- Direct filesystem access is not a tenant authorization mechanism. All user
  downloads must continue to resolve through a tenant-owned parent model.
- The four stale UI expectations from the SAAS-05 checkpoint were corrected
  semantically: current public copy/assets, the Lodgix error fallback asset,
  and the standalone 403 access page are now asserted without skipping tests.

## Release decision

The isolation-focused status is **PASS**, and the SAAS-05.1 controlled staging
baseline is **PASS**. Lodgix remains **NOT SAFE FOR MULTI-TENANT CUSTOMER
PRODUCTION** because property switching/customer onboarding are intentionally
disabled and production rollout still requires an environment-specific release
review.

## Final validation commands

Recorded after the implementation:

```text
npm run build: passed
  public CSS 62.30 kB (11.83 kB gzip)
  public JS 2.99 kB (1.06 kB gzip)
  app CSS 217.49 kB (36.68 kB gzip)
  app JS 48.67 kB (14.72 kB gzip)
php artisan config:cache: passed; application cache cleared afterwards
php artisan route:cache: passed; application cache cleared afterwards
php artisan view:cache: passed; application cache cleared afterwards
git diff --check: passed
full SQLite suite: 169 passed, 1,385 assertions, zero failures
PHP lint: passed for 393 source files
```

**IS LODGIX NOW SAFE FOR CONTROLLED MULTI-TENANT STAGING? YES — the SQLite and
disposable MariaDB hardening checks are green. This is not customer-production
approval; switching and onboarding remain disabled pending a separate release
review.**

SAAS-06 must not begin automatically from this checkpoint.
