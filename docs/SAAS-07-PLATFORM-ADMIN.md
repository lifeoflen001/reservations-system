# SAAS-07 — Lodgix Platform Administration

## Status

SAAS-07 adds a separate, local-only Platform Administration control plane.
This checkpoint is not a production deployment, does not create a production
platform administrator, and does not authorize SAAS-08. The implementation is
intended for controlled staging review after the migration and security
prerequisites below have been completed.

## Identity and route separation

Platform administrators are stored in `platform_administrators` and are
authenticated by the `platform` session guard and `platform_administrators`
provider. Customer staff remain on the existing `web` guard and `/login`
flow. A platform administrator is not an organization membership and does not
receive hotel-tenant access by virtue of having a platform account.

Platform routes are grouped under `/platform` and use the dedicated platform
layout, sidebar, topbar, middleware and CSS entry point:

- `/platform/login`
- `/platform`
- `/platform/organizations` and organization detail
- `/platform/properties` and property detail
- `/platform/subscriptions` (read-only)
- `/platform/support`
- `/platform/health`
- `/platform/audit-logs`
- `/platform/administrators`
- `/platform/profile`

The platform route group is outside customer `tenant.context`. The global
property-settings middleware explicitly skips `platform.*`, so a platform
administrator can never be passed to the customer `TenantContext`, whose
contract accepts only customer `User` identities. Customer organization owners
cannot authenticate on the platform guard, and platform administrators cannot
use the platform guard to access customer routes.

## Platform administrator lifecycle

There is no seeded password or automatic bootstrap credential. Create the first
administrator explicitly in a controlled environment:

```text
php artisan platform:create-admin --name="Platform Operator" --email="operator@example.test"
```

The command prompts for a password when `--password` is omitted, requires at
least 12 characters, hashes the password, and never prints it. Administrators
can be active or disabled. The UI prevents an administrator from disabling
their own account and prevents disabling the last active administrator.

Platform two-factor authentication is mandatory. Password verification creates
only a pending login; the privileged platform guard is established only after
an enrolled TOTP authenticator or a one-time recovery code is verified. New or
previously unenrolled administrators are forced through enrollment and cannot
skip it. Recovery codes are encrypted at rest, displayed only immediately
after generation, and consumed one time. Regeneration invalidates the old
set. Administrators can reset their own authenticator only with the current
platform password plus TOTP/recovery verification; administrator-assisted
reset is intentionally deferred until a separately reviewed recovery process
exists. Login, challenge, enrollment, recovery regeneration and reset paths
are rate limited and audit only safe event metadata.

## Support access

Support access is explicitly started from `/platform/support` with an
organization, property, reason, and duration between 5 and 60 minutes. The
service verifies that the property belongs to the selected organization and
stores the platform administrator, target organization/property, reason,
timestamps, expiry, IP and user agent. Start, expiry and exit events are
written to `platform_audit_logs`.

The active session is visible in the dedicated platform shell, can be ended
explicitly, and is rejected after expiry. A platform administrator cannot
enter a customer workspace merely by visiting a customer URL. SAAS-07.1 adds
a server-enforced, read-only support workspace for reservations, clients,
rooms, room planning, tasks, housekeeping, maintenance, POS, finance and
reports. Every request revalidates the active platform identity, support
permission, session ownership, expiry, organization and property scope.
The support context is separate from customer `TenantContext`; it does not
impersonate a customer, create a customer session, trigger customer jobs or
notifications, or expose mutation/file/export routes. Organization-only
support sessions show safe scope metadata and do not query property-owned
operational records. Credentials, secrets, 2FA fields and recovery material
are redacted from settings and detail views.

## Control-plane data and health

Organizations, properties and subscriptions are displayed through dedicated
read-only platform services. Subscription data is observational only: this
phase adds no billing provider, signup, trial, entitlement, payment, or plan
enforcement behavior.

The health page reports database, queue configuration and failed jobs, cache,
storage, mail configuration, and a deliberately honest scheduler state. The
scheduler is shown as `Not Verifiable` unless a real heartbeat exists; secrets,
connection strings and environment values are never rendered. Storage health
uses a bounded temporary check file on the configured disk and removes it
afterward.

Platform audit records are append-only application records with a UUID, actor,
action, target, optional organization/property scope, safe metadata, IP and
timestamps. Metadata filtering removes password, token, secret, API key,
authorization and cookie fields before persistence.

## Validation

The local validation target is:

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

Run the new migration only against an explicitly disposable SQLite or MariaDB
database. Do not run `migrate:fresh`, `db:wipe`, or destructive commands on a
customer, local operational, or Railway database. The disposable MariaDB run
must verify foreign keys, unique platform identity email/UUID values, support
session relationships, audit-log nullability, and the full existing migration
chain before controlled staging approval.

Protected paths `artifacts/`, `pull/`, and `push/`, environment files,
credentials, local databases, and unrelated rooms work must remain outside
the SAAS-07 change set.

### Local validation record — 2026-10-03

- SQLite: full suite passed, 187 tests and 1,503 assertions.
- Focused platform suite: 14 tests and 86 assertions passed.
- Disposable MariaDB 10.4.32: all 49 migrations applied, the focused platform
  suite passed with 7 tests and 40 assertions, and
  `reservations_pos_integration_saas07_20261003` was dropped and verified
  absent.
- `npm run build`, PHP lint, Composer validation, route cache, view cache and
  `git diff --check` passed for the preceding SAAS-07 baseline; the SAAS-07.1
  validation record is maintained in `docs/SAAS-07-1-PLATFORM-HARDENING.md`.
- The local operational database was not migrated; its platform migration
  remains pending as expected.

## Boundaries and readiness

SAAS-07 does not include public website changes, customer PMS redesign,
billing, signup, trials, entitlement enforcement, platform impersonation,
production deployment, or automatic Git operations. Controlled platform
staging remains subject to a separate explicit deployment review. SAAS-08 is
not authorized by this checkpoint.
