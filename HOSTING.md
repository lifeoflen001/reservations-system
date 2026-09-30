# Lodgix hosting and production runbook

This document describes the production handoff for the Laravel Lodgix PMS in
this repository. It is intentionally operational documentation: it does not
change hotel, reservation, Finance, POS, or accounting behavior.

## Current deployment posture

- Application: Laravel 12, PHP 8.2 or newer, Vite 7, Tailwind CSS 4.
- Public liveness endpoint: `GET /up`.
- Database readiness endpoint: `GET /api/health`; it checks the database and
  storage writability and returns `503` when either check fails.
- Normal application database: MySQL in production.
- Automated tests: SQLite `:memory:` only, as enforced by `phpunit.xml` and
  `.env.testing`.
- Queue default: database queue; Redis is optional.
- Scheduler: `announcements:sync` every minute.
- File storage: local private/public disks by default; use a Railway volume or
  S3-compatible storage for data that must survive a deploy or restart.
- Railway deployment is configured, but this repository does not deploy or
  push automatically from a local validation run.

The public website is configuration-driven and does not query reservations,
clients, payments, balances, staff, or POS data.

## Railway architecture

Use one Railway project with the following services:

1. **Web service**
   - Source: this repository and the intended production branch.
   - Builder: Railpack, as specified in `railway.json`.
   - Build command: `npm run build`.
   - Start command: `php artisan serve --host=0.0.0.0 --port=$PORT`, explicitly
     checked into `railway.json`. For higher traffic, replace this with the
     platform's supported PHP process manager only after a separate capacity
     review.
   - Health check: `/up` (liveness only; it does not require application data).
   - Restart policy: `ON_FAILURE`, maximum three retries in the checked-in
     Railway configuration.

2. **MySQL service**
   - Attach a dedicated production MySQL database to the web service.
   - Map the Railway-provided host, port, database, username, and password to
     Laravel's `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and
     `DB_PASSWORD` variables.
   - Never point this service at `reservations_db` used by a local workstation.
   - Enable TLS/CA verification when the managed database provider requires it.

3. **Worker service**
   - Use the same repository, environment, and MySQL connection as the web
     service.
   - Start command: `php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600`.
   - Keep the worker timeout below `DB_QUEUE_RETRY_AFTER` (the default is 90
     seconds) to prevent the same job being processed concurrently.
   - Monitor `failed_jobs`; retry only after the underlying cause is known.

4. **Scheduler service**
   - Run `php artisan schedule:run` once per minute using Railway's cron
     facility or a dedicated scheduler service.
   - Only one scheduler should be active for this application unless commands
     are deliberately made overlap-safe.

5. **Optional Redis service**
   - Attach only when there is a measured need for shared cache or queue
     throughput.
   - Set `REDIS_URL`/Redis variables and explicitly choose `CACHE_STORE=redis`
     or `QUEUE_CONNECTION=redis`; do not switch financial posting to a cache.

## Railway deployment sequence

The checked-in `railway.json` performs this pre-deploy command:

```text
php artisan migrate --force && php artisan db:seed --class=RbacSeeder --force
```

This is intentionally incremental. Before a production deploy:

1. Confirm the target Railway environment and database connection.
2. Take and verify a database backup.
3. Review the migration list and the release diff.
4. Deploy the web service and observe the pre-deploy output.
5. Verify `/up`, then `/api/health`, login, a read-only reservation page, a
   Finance page, and a POS read-only page.
6. Start/restart the worker and scheduler only after the web release is
   healthy.
7. Watch logs and queue failures during the first business workflow.

Do not replace `migrate --force` with `migrate:fresh`, `migrate:refresh`,
`migrate:reset`, `db:wipe`, `db:seed` without a class, `truncate`, or any raw
`DROP DATABASE`/destructive SQL command. A production rollback is a release
rollback plus a reviewed forward migration or a verified database restore;
rolling migrations backward against live hotel data is not the default plan.

`RbacSeeder` is the appropriate deployment reference seeder: it creates missing
application permissions/roles and reference finance rows, and does not create
demo POS data. Do not replace it with `DatabaseSeeder` on a populated database.
`DatabaseSeeder` contains local/bootstrap behavior, including a default admin
fallback, and is not a production deploy command.

## Environment variables

Use the grouped, secret-free `.env.example` as the variable inventory. Set
real values in Railway's environment-variable UI or secret store.

### Required application variables

```text
APP_ENV=production
APP_KEY=<generated Laravel application key>
APP_DEBUG=false
APP_URL=https://<configured-production-domain>
APP_TIMEZONE=Africa/Dar_es_Salaam
TRUSTED_PROXIES=*
DB_ALLOW_DESTRUCTIVE_COMMANDS=false
HOTEL_SETUP_ENABLED=false
HOTEL_ALLOW_RESERVED_EMAILS=false
```

`APP_URL` must be the real configured HTTPS URL in Railway. Do not commit the
production domain to source or use `localhost` in the production environment.
`APP_KEY` must be generated once for the environment and kept stable; changing
it invalidates encrypted data and sessions.

### MySQL

```text
DB_CONNECTION=mysql
DB_HOST=<Railway MySQL host>
DB_PORT=<Railway MySQL port>
DB_DATABASE=<Railway MySQL database>
DB_USERNAME=<Railway MySQL user>
DB_PASSWORD=<Railway MySQL password>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Do not copy `.env` between environments. Keep local, testing, staging, and
production databases separate.

### Sessions, cache, and queues

The safe baseline is:

```text
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=90
```

The session, cache, queue, failed-job, and job-batch tables must be migrated
before enabling the web/worker services. Redis can be introduced by changing
the relevant store/connection variables together, with a rollback plan.

### Mail

Configure a real provider only in Railway:

```text
MAIL_MAILER=smtp
MAIL_HOST=<provider host>
MAIL_PORT=<provider port>
MAIL_SCHEME=tls
MAIL_USERNAME=<provider user>
MAIL_PASSWORD=<provider secret>
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=<verified sender>
MAIL_FROM_NAME=Lodgix
```

The SMTP timeout is explicit in `config/mail.php`. This Laravel version uses
`MAIL_SCHEME` (for example `tls`); `MAIL_ENCRYPTION` is a legacy variable and
is not consumed by the framework mail configuration. Confirm SPF/DKIM/DMARC
and provider sender verification before sending guest mail. Do not log SMTP
passwords or provider API keys.

### Storage and backups

For a durable local disk, attach a Railway persistent volume and make sure the
mount covers the Laravel storage path used by the service. Validate the exact
mount path in the Railway service rather than assuming a platform path.

For horizontally scalable or restart-safe storage, use the configured S3 disk:

```text
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<secret>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=<region>
AWS_BUCKET=<bucket>
AWS_ENDPOINT=<optional endpoint>
AWS_URL=<optional public URL>
```

Private finance, task, announcement, and backup files must remain private and
be downloaded through their authorized controller paths. Profile avatars use
the public disk by design. Run `php artisan storage:link` after provisioning
the public disk and verify the link. Do not make the private disk public.

Local Railway storage is not a backup. Keep encrypted, off-service MySQL
backups with a retention policy and periodically perform a restore drill into
a disposable database. The configured backup path is
`storage/app/backups`; never store a backup in Git or expose it through the
public disk.

## Database operations

### Safe schema changes

Normal release migrations are incremental:

```bash
php artisan migrate --force
php artisan migrate:status
```

Review every migration for locks, indexes, nullable backfills, foreign-key
ordering, and runtime duration before release. Run long or locking operations
in a maintenance window. Do not run a destructive reset against an existing
hotel database.

### Backup and restore outline

Use the managed MySQL provider's consistent backup facility where available.
For a command-line backup, use a version-matched `mysqldump` with credentials
supplied through a protected environment, then verify the file checksum and
restore it into a disposable database before relying on it.

A restore requires a written incident decision, a maintenance window, a target
database identity check, and post-restore checks for migrations, users,
reservations, folios, payments, invoices, POS orders, and reports. Never test a
restore by overwriting the production database.

### Seeders and reference data

- `RbacSeeder` is idempotent and uses `firstOrCreate` for application-owned
  permissions/roles and finance reference rows.
- `syncWithoutDetaching` preserves existing custom role permissions. Retired
  application permissions are detached only from system roles; custom roles
  are preserved for administrator review.
- `FinanceReferenceSeeder` creates missing accounts/categories and backfills
  only source-linked financial postings that do not already exist.
- `PosDemoSeeder` is local-only and must never run in production.
- `DatabaseSeeder` is for controlled bootstrap/local use, not a routine deploy.
  It can create a default admin when none exists. If a recovery password is
  needed, use a temporary secret, verify login, and remove it immediately.
- `firstOrCreate` does not overwrite administrator changes on existing rows.
  Treat seeded code/name definitions as application-owned defaults and use
  administrator-managed records for custom reference data.

## Storage, exports, and PDFs

PDFs and CSV exports are generated on demand and should be tested with the
production PHP extensions and installed Dompdf dependency. They should not be
treated as durable storage. Uploads and database backups are durable only when
the selected volume/object store is durable.

## Security controls

- `APP_DEBUG=false` is mandatory in production.
- HTTPS is enforced by the production URL configuration; Railway must forward
  the original scheme and `TRUSTED_PROXIES` must match the deployment topology.
- Sessions use secure, HTTP-only, SameSite cookies in production.
- CSRF remains enabled for browser forms; only `webhooks/*` is exempt because
  those requests authenticate with provider signatures.
- API v1 routes require an API token and the `api` throttle.
- Two-factor challenge routes are throttled at six requests per minute.
- Webhook signatures, event IDs, and provider references must be validated
  before financial posting. Inbound webhook event IDs are stored uniquely to
  prevent duplicate processing.
- The application adds `nosniff`, same-origin framing, strict-origin referrer,
  and a restrictive Permissions Policy response header. A CSP should be added
  only after auditing every inline/Vite asset and any required integrations.
- Do not put secrets in Blade, JavaScript bundles, logs, tickets, screenshots,
  or committed environment files.
- Configure Railway log retention or an external sink. Use `LOG_CHANNEL=stderr`
  and `LOG_LEVEL=warning` in production unless a temporary incident requires
  more detail. Review queued-job and webhook error summaries for guest or
  provider data before forwarding logs externally.

## Payments and webhooks

External payment requests are not proof of payment. The safe lifecycle is
`initiated -> pending -> confirmed`, with `failed` and `cancelled` terminal
states. Only verified provider confirmation may create the final posting.

Before enabling a provider, verify:

- credentials are Railway secrets, not source code;
- request/connect timeouts are finite;
- amount, currency, provider reference, and local transaction are compared;
- signatures or authenticated callbacks are verified;
- duplicate callbacks are idempotent;
- failed, cancelled, malformed, and timed-out callbacks leave the PMS usable;
- the provider sandbox is used before any real-money test.

The provider-specific payment gateway controller and the generic inbound
webhook controller must remain behind their signature/idempotency checks. Do
not expose webhook secrets in health responses or logs.

## Performance and observability

Before launch, smoke-test Finance Overview, Transactions, Reconciliation,
Finance Reports, POS Terminal, and POS Sales with representative data. Inspect
query logs for N+1 relationships, confirm indexes for foreign keys/status/date
filters, and record response timings from Railway logs or an approved APM.
Do not publish invented benchmark numbers.

Use application logs, Railway restart events, queue depth, failed jobs,
database CPU/connections/slow queries, storage capacity, and `/api/health` as
the minimum operational signals. `/up` is a liveness check and must remain
fast; it is not a substitute for database readiness monitoring.

## Release and rollback checklist

### Before release

- [ ] Confirm the intended Git commit and review its diff.
- [ ] Confirm Railway environment and MySQL identity.
- [ ] Verify secrets exist only in Railway.
- [ ] Take and verify a database backup.
- [ ] Run the validation commands in the Tests section.
- [ ] Review migrations and expected lock duration.
- [ ] Confirm persistent storage/object storage and `storage:link`.
- [ ] Confirm web, worker, and scheduler commands.
- [ ] Confirm `/up` and `/api/health` behavior.

### After release

- [ ] Check `/up` and `/api/health`.
- [ ] Sign in with a non-default administrator account.
- [ ] Read a reservation and room record.
- [ ] Create a disposable/staged reservation, payment, invoice, POS order,
      room charge, and report verification according to the release plan.
- [ ] Verify one queued email/webhook and one scheduler run.
- [ ] Check queue failures, application errors, database errors, and storage.
- [ ] Remove any one-time bootstrap secret.

### Rollback

1. Stop or pause the release if the new web process is unhealthy.
2. Preserve logs and the release identifier.
3. Roll back the application image/commit only if the schema remains backward
   compatible.
4. If data was changed, use the incident-approved database restore/forward-fix
   plan; do not run a blind migration rollback on live hotel data.
5. Re-run health and financial smoke tests, then resume workers/scheduler.

## Troubleshooting

| Symptom | Checks |
| --- | --- |
| `/up` fails | Inspect PHP process/start command, port binding, build logs, and Railway restart events. |
| `/api/health` returns 503 | Verify MySQL variables, network attachment, migration state, and storage mount permissions. |
| Login loops or CSRF fails | Verify HTTPS forwarding, `TRUSTED_PROXIES`, `APP_URL`, secure cookies, and session table. |
| Queued work is late | Check worker service, `jobs`, `failed_jobs`, timeout/retry settings, and database locks. |
| Uploads disappear | The filesystem is ephemeral or the volume/object store is not mounted/configured. |
| Emails do not send | Check mailer variables, provider verification, `MAIL_TIMEOUT`, worker logs, and queue failures. |
| Duplicate webhook effects | Inspect provider event/reference uniqueness and signature validation before retrying. |
| Assets are missing | Confirm `npm run build`, the generated `public/build` files, and that the release includes them. |
| Migrations stop | Stop the release, inspect the exact migration error, restore/backup if required, and apply a reviewed forward fix. |

## Validation commands

Run from a clean, correctly configured development/test environment. These
commands are non-destructive; none resets a database.

```bash
composer validate
npm run build
php artisan view:cache
php artisan route:cache
php artisan config:cache
php artisan migrate:status
php artisan test
git diff --check
```

The automated suite must continue to use the dedicated SQLite `:memory:` test
connection. MySQL integration tests, when run, must use a separately named,
disposable MySQL database and must never use `reservations_db`.

After validating cached configuration locally, use `php artisan config:clear`
when returning to an uncached local environment. Do not commit generated
runtime caches, `.env`, logs, backups, or `public/build` if the repository's
ignore rules exclude them.

## Phase 11 repository closure

The two Phase 10 repository failures are resolved without restoring the legacy
logo asset or reverting unrelated Rooms work:

1. The Rooms list now renders the existing `x-data.pagination` component. Its
   paginator already uses `withQueryString()`, so search, status, housekeeping,
   floor, tab, and other active query filters remain intact when changing rows.
2. `ProfileSettingsTest` now uses `UploadedFile::fake()->image()` and validates
   the same avatar persistence, response, and removal behavior without reading
   from `public/assets`.

The complete suite is now green: 119 tests passed, 913 assertions. This means
the repository is **READY FOR STAGING**, not production-ready. Railway storage,
backup restoration, production variables, custom domain, and HTTPS still need
to be verified in the controlled staging environment.

## Phase 10 release-candidate closure audit

This section records the final local verification boundary. It is not a
deployment approval and does not claim that a Railway environment has been
configured or deployed.

### Variable inventory

**Required for the web release:**

`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`,
`LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `DB_CONNECTION`, `DB_HOST`,
`DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `CACHE_STORE`,
`SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`,
`SESSION_SAME_SITE`, `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER`,
`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT`,
`FILESYSTEM_DISK`, `TRUSTED_PROXIES`, `DB_ALLOW_DESTRUCTIVE_COMMANDS`,
`HOTEL_SETUP_ENABLED`, and `HOTEL_ALLOW_RESERVED_EMAILS`.

**Optional platform variables:**

`REDIS_URL` or the equivalent Redis host variables, `CACHE_PREFIX`,
`DB_QUEUE_CONNECTION`, `DB_CACHE_CONNECTION`, `AWS_ENDPOINT`, `AWS_URL`,
`DB_DUMP_BINARY`, and `DB_BACKUP_PATH`.

**Integration-specific variables:**

`POSTMARK_API_KEY`, `RESEND_API_KEY`, AWS/S3 credentials, Slack variables,
provider-specific payment credentials, WhatsApp/channel credentials, and
webhook signing secrets. These are entered only in Railway secrets or the
authenticated integration settings; no value belongs in source control.

Railway's MySQL variables must be mapped to Laravel's `DB_*` names. This
repository does not assume undocumented `MYSQLHOST` aliases inside Laravel.

### Database and migration state

The local migration status check completed with no pending migrations. No
production or staging database connection was attempted during this audit.
The safe staging procedure is:

```bash
php artisan migrate:status
GET https://<domain>/api/health
```

Then sign in with a disposable staging account and perform one authenticated
read-only reservation or room lookup. Do not use a reset, destructive seed, or
write operation as a connectivity check.

There are no pending migrations in the current checkout. The latest migration
set creates/extends POS, Finance, staff-catalog, and finance-control tables;
the normal `up` paths are incremental. Review lock duration and foreign-key
ordering in staging before production. Down migrations are rollback metadata,
not an instruction to reverse live financial history.

### Storage decision

**Initial-release decision: Railway persistent volume.** It is the simplest
option for the current single-web-service architecture because the application
already uses Laravel's local private/public disks and database-backed sessions
and queues. A volume is not declared in this repository and no Railway volume
mount path is currently verified. The operator must attach a volume, record the
actual Railway mount path, and ensure it covers the application's `storage/`
directory before release; this document deliberately does not invent a path.

Runtime-written paths are classified as follows:

| Path or data | Classification | Notes |
| --- | --- | --- |
| `storage/app/private/announcements/*` | MUST-PERSIST | Private announcement attachments; authorized downloads. |
| `storage/app/private/tasks/*` | MUST-PERSIST | Private task attachments; authorized downloads. |
| `storage/app/private/finance/*` | MUST-PERSIST | Finance attachments; authorized downloads. |
| `storage/app/backups/*` | MUST-PERSIST/OFF-SERVICE | Database backups; copy outside Railway as well. |
| `storage/app/public/*` | MUST-PERSIST | Public-disk files and the `storage:link` target. |
| `storage/framework/cache/*` | EPHEMERAL-SAFE | Rebuilt cache; database cache is the production default. |
| `storage/framework/sessions/*` | NOT USED BY DEFAULT | Database sessions are required for restart-safe sessions. |
| `storage/logs/*` | TEMPORARY | Prefer Railway stderr logging and external retention. |
| `public/build/*` | BUILD OUTPUT | Recreated by `npm run build`; not user data. |
| streamed PDFs/CSV exports | TEMPORARY | Generated on demand and not durable records. |
| profile avatar database content | MUST-PERSIST | Current profile controller stores avatar content in the users table. |

After attaching the volume, run `php artisan storage:link`, verify the link,
upload a disposable staging file, restart the service, retrieve it, and remove
it. A volume does not replace MySQL backups. S3 remains a future option; if it
is selected later, set `FILESYSTEM_DISK=s3` and all required AWS variables as
one reviewed change rather than mixing local and S3 paths accidentally.

### Queue and scheduler decision

**Worker required: YES.** The application queues email delivery, outbound
webhooks, and booking-channel synchronization. `SendHotelEmail`,
`DeliverWebhook`, and `SyncBookingChannel` each use three tries and backoff
intervals of 30, 120, and 600 seconds. The exact worker command is:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600
```

`failed_jobs` uses the database-UUID driver. Operators may inspect with
`php artisan queue:failed`, retry a reviewed job with `php artisan queue:retry
<id>`, or forget a reviewed job with `php artisan queue:forget <id>`. Do not
discard failed financial or integration work without reconciliation.

**Scheduler required: YES.** `announcements:sync` runs every minute. Configure
one Railway cron/scheduler service to invoke:

```bash
php artisan schedule:run
```

once per minute. No scheduled task in this checkout performs a destructive
database operation.

### Health, headers, and disclosure

- `/up` is the lightweight Laravel liveness endpoint and does not call external
  APIs or require database readiness.
- `/api/health` returns only `status`, `checks.database`, `checks.storage`, and
  an ISO timestamp. It does not return database hosts, database names,
  credentials, paths, environment values, stack traces, or provider secrets.
- The global security middleware was verified on the liveness response and is
  expected on `/`, `/login`, `/dashboard`, `/finance/overview`, and
  `/pos/terminal`: `nosniff`, same-origin framing, strict-origin referrer, and
  restrictive Permissions Policy.
- CSP is intentionally deferred. The public and authenticated Vite/inline
  asset surface must be inventoried before adding it; adding it blindly could
  break the working UI.
- Railway's forwarded HTTPS scheme must be trusted consistently. Verify
  `request()->secure()`, generated URLs, cookies, and redirects behind the
  proxy before custom-domain release.

### Mail procedure

Use a verified staging sender and one controlled recipient. Confirm one queued
message reaches `sent`, then temporarily make the provider unavailable and
confirm the job moves through retry/failure handling without exposing the SMTP
secret. Inspect the queue and email delivery log, restore the provider, and do
not bulk-send during release verification.

### Domain and HTTPS procedure

1. Deploy to the Railway-provided domain in staging.
2. Attach the final custom domain and configure DNS as Railway specifies.
3. Confirm HTTPS is active and forwarded scheme handling is correct.
4. Set the production `APP_URL` to the final HTTPS origin.
5. Run `php artisan config:clear`, then `php artisan config:cache` in the
   target environment.
6. Verify canonical metadata, `/sitemap.xml`, `/robots.txt`, password-reset
   links, email action URLs, and Open Graph URLs.

Local defaults such as `http://localhost` exist only as framework fallbacks;
production `APP_URL` must override them. No public metadata route hard-codes a
production domain.

### Initial administrator procedure

For a new environment, take a backup/identity checkpoint, temporarily enable
the controlled setup flow with `HOTEL_SETUP_ENABLED=true`, create the first
administrator with a newly generated password through `/setup`, verify login,
then set `HOTEL_SETUP_ENABLED=false` and clear/cache configuration. Remove any
temporary bootstrap secret. Do not rely on the `DatabaseSeeder` fallback
password and do not publish default credentials. Existing installations must
use an administrator-created staff account or an approved recovery procedure.

### Release blocker classification

- **BLOCKER:** no verified Railway persistent storage mount for user files and
  backups; no verified production MySQL backup/restore drill; no verified
  Railway environment/domain/HTTPS configuration.
- **HIGH:** the two known full-suite failures remain unresolved unless a release
  owner explicitly signs the waiver described below.
- **MEDIUM:** CSP is deferred; Redis is not required for the initial release.
- **LOW:** Composer reports the existing unbound `laravel/jetstream` `*`
  constraint warning.
- **INFORMATIONAL:** local defaults and local-only test services are not
  production values.

### Release decision

The release candidate is **NOT READY** for production deployment from this
workspace. Code-level verification is strong, but the external Railway
environment, durable storage, backup/restore proof, custom domain, and two
known test failures are not cleared. This is a verification result, not a
request to deploy.

## Exact Railway commands

| Service | Command |
| --- | --- |
| Build | `npm run build` |
| Pre-deploy | `php artisan migrate --force && php artisan db:seed --class=RbacSeeder --force` |
| Web | `php artisan serve --host=0.0.0.0 --port=$PORT` |
| Worker | `php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600` |
| Scheduler | `php artisan schedule:run` once per minute |

## Final deployment order

1. Create the Railway project.
2. Add and identity-check MySQL.
3. Attach and test the persistent storage volume.
4. Configure required, optional, and integration secrets.
5. Configure the web service and explicit start command.
6. Configure the worker.
7. Configure the one-minute scheduler.
8. Configure the custom domain and HTTPS.
9. Verify a restorable backup.
10. Deploy the reviewed release.
11. Observe the incremental pre-deploy migration and RBAC seeding output.
12. Warm/verify caches and storage link.
13. Smoke-test health, authentication, public routes, Finance, and POS.
14. Verify one queue action, one storage round trip, and one controlled mail.
15. Monitor errors, failed jobs, database health, storage, and HTTPS.

## Rollback decision tree

- **Application code failure:** stop the release, preserve logs, roll back the
  application commit/image if the schema remains compatible, then smoke-test.
- **Migration failure:** stop deployment, preserve the exact error, take a
  backup if possible, and apply a reviewed forward fix or restore to a verified
  staging target. Never run a blind destructive reset or rollback.
- **Queue failure:** keep financial records authoritative, inspect `failed_jobs`,
  correct the provider/worker cause, then retry reviewed jobs.
- **Storage failure:** stop uploads that could be lost, verify volume/S3
  attachment and permissions, and restore from the storage backup/replica.
- **Mail failure:** leave delivery records pending/failed, correct the provider,
  and retry controlled messages only.
- **Domain/HTTPS failure:** keep the Railway domain available, correct DNS or
  proxy configuration, then update `APP_URL` only after HTTPS is confirmed.
