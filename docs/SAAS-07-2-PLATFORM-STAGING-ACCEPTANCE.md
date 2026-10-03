# SAAS-07.2 — Platform Staging Acceptance

## Status

**PASS WITH ISSUES — local control-plane checkpoint validated; real Railway
staging is not certified.**

SAAS-07.2 requires a separately isolated Railway staging environment before
Platform Administration can be signed off. The Railway project inspected for
this checkpoint currently exposes only the `production` environment. The
environment selector offers creation of a new environment, but no staging
environment, database, storage, queue, scheduler or sandbox mail boundary was
available to use. No new Railway environment was created, and production was
not used for testing.

## Acceptance matrix

| Area | Test | Result | Evidence | Severity | Remediation |
|---|---|---|---|---|---|
| Source checkpoint | Local SAAS-07/07.1 source reviewed | PASS | Explicit source list; protected paths excluded | — | Keep staging commit separate from later fixes |
| Local security | Platform 2FA, recovery, reset, rate limits | PASS | 14 focused tests / 86 assertions | — | Repeat in real staging |
| Local support | Ownership, expiry, exit, read-only route boundary | PASS | Focused support tests; 403/405 denial coverage | — | Repeat against synthetic Alpha/Beta staging data |
| Local regression | Full SQLite suite | PASS | 194 tests / 1,549 assertions | — | Repeat after staging-only configuration is supplied |
| Build | Vite production build | PASS | `npm run build` | — | — |
| MariaDB schema | Full disposable migration chain | PASS | Disposable MariaDB schema applied and removed; remaining count 0 | — | Repeat with staging database after provisioning |
| Railway environment | Isolated non-production environment | NOT RUN | Only `production` is present in Railway | HIGH | Create a separate staging environment/project |
| Railway deployment | Deploy checkpoint to staging | BLOCKED | No isolated staging target; automatic push is not authorized by this phase | HIGH | Explicitly authorize source push and provide staging target |
| Browser HTTPS QA | Login, 2FA, cookies, CSRF, support flows | NOT RUN | Requires real staging URL and synthetic credentials | HIGH | Run browser acceptance only over staging HTTPS |
| Queue worker | Synthetic job and failed-job evidence | NOT RUN | No isolated staging worker supplied | HIGH | Provision staging worker and sandbox queue |
| Scheduler | Real scheduled execution/heartbeat | NOT RUN | No isolated staging scheduler supplied | HIGH | Provision scheduler or document unverifiable state |
| Storage | Persistent upload and restart test | NOT RUN | No isolated staging volume/bucket supplied | HIGH | Attach staging-only persistent storage |
| Mail | Sandbox delivery | NOT RUN | No staging mail sink/provider supplied | HIGH | Configure non-production mail delivery |
| Backup/restore | Staging dump and disposable restore | NOT RUN | No staging database available | HIGH | Back up staging only and restore to a separate disposable DB |
| Responsive/dark/accessibility | Required browser viewport matrix | NOT RUN | Requires staging browser walkthrough | MEDIUM | Complete manual QA on platform and support pages |

## Local evidence

- Mandatory platform 2FA is enforced: password-only login remains pending,
  enrollment cannot be skipped, TOTP/recovery verification is required, and
  recovery codes are one-time and encrypted.
- Platform and customer guards are separate. Platform logout rotates and
  destroys the previous session ID while preserving the independently
  authenticated customer guard in the same browser session.
- Support context is derived only from the active, owned support session. It
  revalidates administrator, permission, organization, property, expiry and
  session ownership on every request.
- Support workspace queries are scoped through `PlatformSupportContext` and
  server-side read-only services. No support mutation, file, or bulk-export
  route is exposed; non-GET attempts are denied.
- Support pages send `private, no-store, max-age=0` and `Pragma: no-cache`.
- Platform audit data is append-only through the available application paths;
  no edit or delete route is exposed.
- Health reporting does not call queue configuration “Healthy” without worker
  evidence; scheduler remains `Not Verifiable` without a heartbeat.

## Required staging gate

Before certification, provision a separate Railway staging boundary with:

- a clearly non-production web service and deployment history;
- a new MariaDB/MySQL database with different identity and credentials;
- staging-only persistent storage, queue, cache and sandbox mail;
- staging-only domain/session-cookie configuration and HTTPS;
- synthetic Alpha/Beta organizations and properties only;
- access for a dedicated staging Platform Administrator created with
  `php artisan platform:create-admin`.

Run `php artisan migrate --force` only after verifying the connection is the
staging database. Never use `migrate:fresh`, `migrate:refresh`, `migrate:reset`
or `db:wipe` on staging or production.

## Severity decision

There is no local blocker in the implemented security tests. Staging remains
uncertified because the required real-environment evidence is unavailable.
The missing isolated staging boundary, worker, scheduler, storage, mail and
backup/restore evidence are **HIGH** release-gate issues for SAAS-07.2. No
production deployment, migration, credential creation or customer-data test
was performed.

SAAS-08 is not authorized by this checkpoint.
