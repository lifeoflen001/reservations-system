# SAAS-06.2 staging acceptance and baseline freeze

Date: 2026-10-02
Scope: customer-side multi-tenant foundation only
Environment used for automated checks: isolated SQLite and disposable MariaDB
Production deployment: not performed by this phase

## Release verdict

| Area | Result | Evidence / limitation |
|---|---|---|
| Automated multi-tenant security | PASS | Existing SAAS isolation suites plus explicit owner tests pass. |
| Manual browser QA | PARTIAL | The repository has no separate staging credentials or approved synthetic staging dataset in this workspace; no production data was used. |
| Staging infrastructure | PARTIAL | Application health endpoints and Railway configuration are present, but a separately controlled staging environment was not provided for this phase. |
| Backup / restore | PARTIAL | The application has a guarded database backup service and documented procedure below; a disposable MariaDB restore run requires a staging database/backup target. |
| Repository baseline | READY WITH REVIEW | Required SaaS changes and documentation are identified; protected directories remain excluded. |

## Test matrix

| Feature | Tenant | Device | Result | Issue | Resolution |
|---|---|---|---|---|---|
| Explicit owner backfill | Existing organizations | Automated | PASS | None | Highest-authority active membership selected deterministically; ties use lowest membership id. |
| Multiple owners | Alpha-style organization | Automated | PASS | None | `is_owner` is independent of role and supports more than one active owner. |
| Last-owner protection | Current organization | Automated | PASS | None | Removal and deactivation of the final active owner are rejected server-side. |
| Ownership audit | Current organization | Automated | PASS | None | Grant, remove, and deterministic backfill create organization audit records. |
| Non-owner ownership change | Current organization | Automated | PASS | None | Owner routes require active owner authority and tenant membership. |
| Cross-organization ownership change | Alpha to Beta | Automated | PASS | None | Target membership is checked against the resolved organization. |
| Members UI | Current organization | Automated view coverage | PASS | None | Owner badge and owner-only actions are rendered in the member workflow. |
| Dashboard / reservations / rooms | Alpha and Beta | Browser staging | NOT RUN | No controlled staging login and synthetic fixture supplied. | Execute the SAAS-06.2 browser runbook before SAAS-07. |
| POS / finance / search / exports | Alpha and Beta | Browser/API staging | PARTIAL | Automated isolation exists from prior phases; live browser walkthrough was not run in a separate staging environment. | Repeat with Alpha/Beta fixture and test token. |
| Storage persistence | Tenant-owned files | Staging | NOT RUN | No staging volume or S3 bucket was supplied. | Verify restart/redeploy persistence on the selected staging storage. |
| Queue / scheduler / sandbox mail | Alpha and Beta | Staging | NOT RUN | No staging worker, scheduler, or sandbox SMTP endpoint was supplied. | Run sequential synthetic jobs and inspect tenant context in logs. |
| HTTPS / proxy / cookies | Staging URL | Browser | PARTIAL | Railway production configuration is visible, but separate staging acceptance was not available. | Verify secure cookies, CSRF, redirects, and forwarded HTTPS headers on staging. |
| Database backup / restore | Disposable database | CLI | PARTIAL | Backup service and guarded download path exist; no disposable MariaDB backup target was provided for this phase. | Create a staging dump, restore to a separate disposable DB, and reconcile counts/totals. |
| Responsive / dark mode / keyboard | Customer administration | Browser | NOT RUN | Requires the controlled staging browser pass. | Check all required viewports and keyboard paths before SAAS-07. |

## Organization ownership

`organization_memberships.is_owner` is the explicit ownership indicator. It is
tenant-bounded and does not grant Platform Admin access. Owners receive the
customer-side organization administration permissions needed to manage settings,
properties, memberships, access, and audit visibility.

The migration backfill selects one active membership per organization when no
active owner exists. Authority is scored from the existing RBAC model:

1. `super_administrator`
2. `administrator`
3. `manager`
4. an active role with `members.manage`
5. any other active membership

Within an equal authority score, the lowest membership id wins. The selection
is written to `organization_audit_logs` with a system actor (`NULL`) because it
is migration-owned, and the `saas:backfill-organization-owners` command prints
the exact organization, membership, user, role, and score selected.

Multiple owners are supported. Owner grant and removal are audited. The final
active owner cannot be removed or deactivated. Ownership changes are always
resolved within the current organization; an owner is not a Platform Admin and
cannot access another organization through ownership.

## Staging runbook

Use only synthetic data in a disposable/staging database:

- Organization Alpha: Alpha Hotel, Alpha Lodge; Alpha Owner, Alpha Manager,
  Alpha Single-Property User.
- Organization Beta: Beta Resort; Beta Owner.
- Use distinct room, reservation, POS, finance, expense, and task identifiers
  for every property.

Run migrations from a clean staging baseline, then run the safe backfills:

```text
php artisan migrate --force
php artisan saas:backfill-default-tenant --validate
php artisan saas:backfill-operational-ownership
php artisan saas:backfill-organization-owners --dry-run
php artisan saas:backfill-organization-owners
php artisan db:seed --class=RbacSeeder --force
```

Do not run `migrate:fresh`, `db:wipe`, `schema:drop`, or any equivalent command
against operational or production data. The application command guard also
blocks destructive database commands unless an explicitly disposable SQLite
database is configured.

Verify `/up` and `/api/health`; neither endpoint should expose tenant data,
credentials, or secrets. Review logs during tenant switching and direct URL/API
denial tests for 403/404 responses, SQL errors, tenant-context failures, and
queue failures.

## Backup and restore procedure

The existing `DatabaseBackupService` writes guarded SQL backups to the configured
`DB_BACKUP_PATH` (default `storage/app/backups`) and supports the configured
SQLite workflow. For MariaDB/MySQL staging, use the provider's encrypted,
access-controlled dump facility or `mysqldump` with credentials supplied only
through the staging environment:

```text
mysqldump --single-transaction --routines --triggers \
  --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" "$DB_DATABASE" > staging.sql
mysql --host="$RESTORE_DB_HOST" --port="$RESTORE_DB_PORT" \
  --user="$RESTORE_DB_USERNAME" --password="$RESTORE_DB_PASSWORD" \
  "$RESTORE_DB_DATABASE" < staging.sql
php artisan migrate:status
php artisan saas:backfill-default-tenant --validate
```

Never commit the dump or credentials. After restore, compare organization and
property counts, membership counts, reservation counts, POS order counts,
payment totals, and ledger totals against the source staging database. Tenant
owned files must be backed up from the configured `FILESYSTEM_DISK`; public CMS
media remains platform-global and must be included separately. Railway volume
snapshots or S3 versioning/export are infrastructure responsibilities and must
be proven in the selected staging environment.

## SAAS-07 boundary recommendation

Do not implement Platform Admin in this phase. Keep these concepts separate:

- Platform Administrator: Lodgix-level operational authority, outside customer
  organizations.
- Organization Owner: highest customer-side authority, bounded to one
  organization.
- Organization Admin: delegated organization administration without assuming
  Platform Admin authority.
- Property Manager: property-scoped operational authority.

Based on the current repository, SAAS-07 should share the existing `users`
identity table only if it adds explicit platform authorization separate from
organization membership and adds dedicated policies/audit boundaries. A
separate identity table is not required yet, but organization ownership must
never be treated as platform authorization.

## Known issues and gate

### BLOCKER

None found in the automated ownership/isolation scope.

### HIGH

None found in the automated ownership/isolation scope. Manual staging QA is
still required before a production or SAAS-07 decision.

### MEDIUM

- Separate staging infrastructure, worker, scheduler, sandbox mail, storage,
  and disposable backup/restore targets were not available to this workspace.
- Manual browser coverage for the full Alpha/Beta matrix remains pending.

### LOW

- Visual polish and performance observations should be recorded during the
  controlled browser pass.

## Entry decision

Is the current customer-side SaaS foundation ready for controlled staging? **YES**
for a controlled, synthetic-data staging run; **NO** for production approval.

Is the repository ready for a SaaS foundation checkpoint? **YES**, subject to
review of the exact files listed in the phase report.

Is Lodgix ready to begin SAAS-07 Platform Admin? **NO**. Complete the manual
staging, infrastructure, backup/restore, storage, worker, scheduler, mail,
HTTPS, responsive, dark-mode, and accessibility checks first.
