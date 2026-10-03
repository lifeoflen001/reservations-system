# SAAS-07.1 — Platform Security Hardening and Support Operations

## Scope

This phase hardens the SAAS-07 platform control plane locally. It does not
deploy, create production credentials, modify the customer PMS UX, authorize
SAAS-08, or perform automatic Git operations. Customer authentication remains
on the `web` guard; platform administration remains on the separate `platform`
guard.

## Mandatory platform 2FA

- Password verification creates a pending platform login only.
- An enrolled administrator must verify a TOTP code or a one-time recovery
  code before the platform guard is authenticated.
- An unenrolled administrator is forced to enroll and cannot skip enrollment.
- Fortify's encrypted secret and recovery-code storage are used on the
  `PlatformAdministrator` model; plaintext secrets/codes are not persisted.
- Recovery codes are displayed only immediately after enrollment or
  regeneration, and successful recovery-code use removes that code.
- Self-reset requires the current platform password and a valid TOTP/recovery
  factor, then invalidates the platform session. Administrator-assisted reset
  is deferred pending a separately reviewed break-glass process.
- Failed password, enrollment, challenge, recovery-regeneration and reset
  attempts are rate limited. Audit events record outcomes and actor/scope
  metadata only; secrets, codes, passwords, tokens and cookies are filtered.

## Support workspace security model

Support access is a time-bounded record tied to one platform administrator,
organization and optional property. The route-bound session is revalidated on
every workspace request for active platform identity and permission, session
ownership, active/expired state, organization/property status and configured
expiry. `PlatformSupportContext` is a scoped request service independent of
customer `TenantContext`; it derives scope only from the active support-session
record and never accepts a query ID as authorization.

The read-only workspace has server-enforced query methods for dashboard,
reservations, clients, rooms, room planning, tasks, housekeeping, maintenance,
POS, finance, reports and safe settings metadata. Non-GET support requests are
blocked and audited. No support mutation, file download, upload or bulk-export
route is exposed. Viewing the workspace does not impersonate a customer,
create customer notifications, dispatch customer jobs, or change membership.
Organization-only support is limited to safe scope metadata because operational
records are property-owned. Settings and detail views redact password, token,
secret, API key, authorization, cookie, 2FA and recovery-code fields.

## Health and audit boundaries

Database/cache/storage checks are actually performed. Queue and mail states are
labelled as configuration evidence, not worker or delivery proof; queue
processing is not called healthy without a worker heartbeat. Scheduler remains
unverified until a real heartbeat exists. Platform audit logs are append-only
application records and omit secret metadata.

## Validation checklist

```text
php artisan test --filter=PlatformAdministrationTest
php artisan test
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan route:list --path=platform
git diff --check
```

The MariaDB check must use a disposable schema whose name begins with
`reservations_pos_integration_`. Apply the full migration chain and relevant
tests there, then drop that exact schema and verify it no longer exists.
Never run `migrate:fresh`, `db:wipe`, or any destructive command against the
operational or Railway database.

### Local validation record — 2026-10-03

- Focused platform suite: 14 tests and 86 assertions passed.
- Full SQLite suite: 194 tests and 1,549 assertions passed.
- Vite production build, PHP lint, route cache, view cache and
  `git diff --check` passed.
- The full migration chain, including the platform control-plane and support
  lifecycle migrations, applied successfully to disposable MariaDB
  `reservations_pos_integration_saas071_20261003`; the schema was then dropped
  and verified absent.
- MariaDB PHPUnit execution is intentionally refused by `tests/TestCase.php`,
  which requires isolated SQLite for automated tests. This safety guard was
  not bypassed.
- The operational database and Railway database were not migrated.

Protected `artifacts/`, `pull/`, and `push/` directories, environment files,
credentials, local databases and unrelated rooms work are excluded from this
phase. No production deployment or Git commit is part of SAAS-07.1.
