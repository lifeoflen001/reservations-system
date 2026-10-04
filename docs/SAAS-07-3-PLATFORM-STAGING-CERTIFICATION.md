# SAAS-07.3 — Platform Staging Certification

## Status

**FAIL — isolated Railway staging could not be provisioned.**

The local Platform Control Plane checkpoint is complete at `a0bdd64`, but that
commit is not present on `origin/main`. Railway therefore cannot deploy the
checkpoint from the current remote source. The phase explicitly requires a
stop before pushing unless push authorization is separately provided.

Railway inspection also confirmed that the project currently exposes only the
`production` environment. The environment selector offers `New Environment`,
but no isolated staging environment or staging service boundary currently
exists. No Railway environment was created, and production was not touched.

## Acceptance matrix

| Area | Test | Result | Evidence | Severity | Remediation |
|---|---|---|---|---|---|
| Source revision | Verify checkpoint is committed | PASS | Local HEAD `a0bdd64` | — | Keep this revision unchanged for staging |
| Remote source | Verify Railway can see checkpoint | BLOCKED | `main` is ahead of `origin/main` by one commit | HIGH | Explicitly authorize `git push origin main` |
| Railway environment | Isolated staging exists | FAIL | Railway environment selector shows only `production` and `New Environment` | HIGH | Create a separate `staging` environment |
| Production safety | No production mutation | PASS | No deploy, restart, migration, seed, variable, storage or data mutation performed | — | Preserve this boundary |
| Web service | Staging deployment | NOT RUN | No isolated target and checkpoint is not remote | HIGH | Push approved checkpoint, then deploy staging only |
| Database | New staging MariaDB/MySQL | NOT RUN | No staging database available | HIGH | Provision a new database and verify identity differs from production |
| Storage | Persistent staging storage | NOT RUN | No staging volume/object store available | HIGH | Provision staging-only persistent storage |
| Queue | Real worker execution | NOT RUN | No staging worker available | HIGH | Provision a staging worker and synthetic jobs |
| Scheduler | Real scheduled execution | NOT RUN | No staging scheduler available | HIGH | Provision scheduler and verify heartbeat/tasks |
| Mail | Sandbox delivery | NOT RUN | No staging mail sink/provider available | HIGH | Configure non-production mail delivery |
| HTTPS/cookies/CSRF | Real browser acceptance | NOT RUN | No staging URL exists | HIGH | Run browser acceptance over staging HTTPS |
| Platform 2FA | Enrollment/challenge/recovery/reset | PASS locally | 14 focused tests / 86 assertions | — | Repeat over staging HTTPS |
| Support workspace | Scope/read-only/expiry/exit | PASS locally | Focused support and isolation tests | — | Repeat with synthetic Alpha/Beta tenants |
| Customer SaaS | Regression suite | PASS locally | 194 tests / 1,549 assertions | — | Repeat after staging provisioning |
| Build and caches | Production build/cache validation | PASS locally | Build, config, route and view cache passed | — | Re-run on staging revision |
| Backup/restore | Staging backup reconciliation | NOT RUN | No staging database available | HIGH | Back up staging and restore to a new disposable DB |
| Responsive/dark/accessibility | Manual browser QA | NOT RUN | Requires staging browser surface | MEDIUM | Complete required viewport and keyboard matrix |

## Local security evidence

The local checkpoint proves the following without claiming real staging
acceptance:

- Password-only Platform Admin login remains pending until mandatory TOTP or a
  recovery code is verified.
- Recovery codes are encrypted and one-time; regeneration replaces prior
  codes.
- Platform/customer guard separation and platform logout behavior are tested.
- Support context is creator-bound, organization/property-scoped,
  expiry-checked and independent from customer `TenantContext`.
- Support workspace mutation attempts have no exposed mutation endpoint and are
  server-denied; support pages use private no-store headers.
- Support queries, settings redaction, audit events and health reporting are
  covered locally.
- The full migration chain applied successfully to a disposable MariaDB
  schema; the schema was removed and verified absent.

## Required next actions

1. Confirm the exact staging environment/project and its non-production
   database, storage, queue, scheduler, cache and mail boundaries.
2. Explicitly authorize pushing the local checkpoint, if desired. The exact
   command is:

   ```text
   git push origin main
   ```

3. Create/use a separate Railway `staging` environment only after confirming
   the target before every mutation.
4. Generate a staging-only `APP_KEY`, configure `APP_ENV=staging` and
   `APP_DEBUG=false`, then run only `php artisan migrate --force` against the
   verified staging database.
5. Create only synthetic Platform Admin and Alpha/Beta tenant data, then run
   the real browser, worker, scheduler, storage, mail and backup/restore
   acceptance matrix.

No password, API key, APP_KEY, database credential, production data or staging
credential is stored in this repository or this document.

## Certification decision

Platform staging is **not certified**. The unresolved remote-source,
environment, database, storage, queue, scheduler, mail and backup/restore
gates are HIGH issues for this phase. SAAS-08 remains unauthorized.
