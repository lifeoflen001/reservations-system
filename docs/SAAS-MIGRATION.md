# Lodgix SaaS migration foundation

This document records the Phase SAAS-02 foundation. It is intentionally not a
claim that Lodgix is fully multi-tenant yet.

## Architecture decisions

- `organizations` is the future tenant boundary.
- `properties` are children of an organization and are the intended operating
  unit for hotel, POS, finance, and guest data.
- `users` remain platform identities. Organization membership and role context
  are represented by `organization_memberships`, while property access is
  represented explicitly by `property_memberships`.
- The current database remains shared. Tenant and property query scoping is
  not enabled in this phase.
- A user may belong to more than one organization. A membership may have
  access to more than one property.
- `plans` and `subscriptions` are a minimal subscription skeleton only. They
  do not enforce billing, limits, or entitlements.

The intended future direction is organization-scoped guest data, property-
scoped POS and finance data, and platform administration using the same user
identity with separate platform authorization. Those changes belong to later
phases.

## Phase SAAS-02 additions

The migrations add:

- `organizations`: stable UUID, human-readable slug, lifecycle status,
  regional defaults, billing contact, and subscription status.
- `properties`: nullable organization link for safe rollout, stable UUID,
  organization-local slug/code, lifecycle status, and reserved JSON settings.
- `organization_memberships`: unique user/organization membership with a
  legacy-compatible role reference and active/inactive status.
- `property_memberships`: explicit membership-to-property access rows with a
  unique pair and a model-level cross-organization consistency check.
- `plans` and `subscriptions`: status-based subscription records without
  pricing or entitlement behavior.

Foreign keys use restrictive deletion for organizations, properties, and
subscriptions where removing a parent could orphan operational context. User
and membership cleanup cascades where the relationship is purely dependent;
role and inviter references are nullable. No soft-delete policy is introduced
by this phase.

## Existing-data backfill

`SaasDefaultTenantBackfillService` is used by migration, seeding, and the
setup-completion path. It is safe to run repeatedly:

1. It derives one default organization from the existing property name or the
   configured default property name.
2. It links existing properties without an organization to that organization.
3. It assigns missing property UUIDs, slugs, and `PROP-{id}` codes without
   replacing existing values.
4. It creates one organization membership per existing user and copies the
   legacy `users.role_id` when present.
5. It creates explicit access rows for every backfilled membership/property
   pair.

An empty schema does not receive an orphan organization. A new organization is
created once a property or user exists. Existing organization links and
membership/access rows are preserved. The `saas:backfill-default-tenant`
command supports `--dry-run` and `--validate` for review.

## Compatibility boundaries

This phase deliberately leaves the following legacy behavior in place:

- `users.role_id` remains the current application authorization role.
- The existing `Gate::before` super-administrator behavior remains unchanged.
- `Property::first()` and the single-property setup flow remain unchanged.
- Operational tables do not yet receive `organization_id` or `property_id`.
- Existing global property configuration/cache keys remain global:
  `hotel.property-settings`, `hotel.active-currencies`,
  `hotel.active-languages`, and `hotel.integrations`.
- Existing API tokens, CMS data, website contact enquiries, notifications,
  and application bindings remain outside tenant scoping.
- The existing website marketing pricing table is not reused as the SaaS plan
  catalog.

Consequently, Lodgix is **not fully multi-tenant** after this phase. No
tenant context middleware, global scopes, route changes, controllers, signup,
onboarding, billing, or business-module authorization is introduced here.

## Rollback and safety

The backfill migration has a deliberate no-op `down()` because deleting or
merging organizations, memberships, or property identifiers could destroy
information. Schema rollback should be reviewed as a controlled migration
operation, and must not be replaced with `migrate:fresh`, `db:wipe`, truncation,
or other destructive commands against an operational database.

## Next phase requirements

Before enabling tenant-aware behavior, the next phase must define a request
tenant/property context, authorization rules, resolver precedence, migration
of each operational table, cache-key changes, job/event context propagation,
API token context, and tests proving that cross-tenant reads and writes are
impossible. Only after those controls are complete should product onboarding,
billing, or entitlements be added.

## Phase SAAS-06.1: customer-side tenant administration

SAAS-06.1 completes the customer-side administration boundary needed before
Platform Admin work:

- organization profile settings are current-organization scoped;
- existing organization memberships can be searched and managed without
  exposing passwords, 2FA secrets, or global user enumeration;
- property access is explicit, organization-validated, and manageable from both
  member and property views;
- membership and property access changes are centralized in
  MembershipAccessService;
- organization, property, membership, access, and successful context-switch
  events are recorded in immutable organization_audit_logs;
- inactive properties remain visible to authorized organization administrators
  but are excluded from normal context selection;
- invitations, billing, signup, subscription enforcement, and Platform Admin
  remain outside scope.

The existing schema has no formal organization-owner concept. The current
safe behavior prevents self-deactivation and removing the last active
membership, while a dedicated owner model remains a later design decision.
docs/SAAS-06-1-STAGING-CHECKLIST.md is the controlled staging sign-off
procedure.

## Phase SAAS-03: context foundation

SAAS-02 was revalidated before this phase on the disposable MariaDB database
`reservations_pos_integration_saas03_pre_20261002`. All migrations and the
SAAS foundation tests passed; the database was removed immediately afterward.

`App\Services\Tenancy\TenantContext` is now the single request-scoped source
for the current organization and property. Its session keys are explicitly
namespaced as `tenant.organization_id` and `tenant.property_id`. Resolution:

1. reads the session preference;
2. accepts it only when the user has an active membership and the organization
   is administratively active;
3. selects the deterministic lowest-ID active membership otherwise;
4. accepts a property only when active property access exists under that
   membership and the property belongs to the organization;
5. selects the deterministic lowest-ID allowed property otherwise; and
6. rewrites or clears the session values after validation.

Membership and property-access changes therefore take effect on the next
request. Administrative organization status and property status are checked;
subscription status is not enforced. Zero-access users receive a safe 403
state instead of a server error.

The `tenant.context` middleware runs after authentication, active-user checks,
installation/session checks, and password-change enforcement. It is applied to
authenticated hotel/PMS routes. Website CMS routes and management contact
enquiries are explicitly platform-global and are excluded from tenant context.
Public marketing routes, metadata, login, sitemap, and robots remain outside
the middleware.

Login resolves the default context after session regeneration. Logout clears
both tenant session keys before invalidating the session. No static or global
request state is used; the service is container-scoped so it is safe for
request lifetimes and does not leak into long-running workers.

Property switching is implemented as a validated service foundation only. No
normal production UI or active switcher is exposed yet because reservations,
rooms, tasks, POS, finance, and other operational tables are still globally
unscoped. This is **not safe for multi-tenant customer production**. A future
switching endpoint/UI must remain feature-gated until SAAS-04/05 completes
operational ownership and authorization isolation.

`PropertySettingsService` now reads the current property from `TenantContext`
and no longer uses `Property::first()` to choose the active property. Its
property cache is keyed by organization/property UUIDs, while currencies and
languages remain platform-global reference-data caches because their current
configuration is global. Property settings are therefore isolated between
contexts without a global cache flush.

Remaining singleton assumptions are intentionally documented for later work:

- `Property::first()` in tests and setup/default-data assertions: compatibility
  coverage only; SAAS-04 should replace operational assumptions systematically.
- `Property::updateOrCreate(['id' => 1], ...)` in initial setup: installation
  bootstrap behavior; retain until setup becomes tenant-aware.
- `PropertySettingsService` has no property fallback for authenticated users
  without context; it returns platform defaults only where a caller requests a
  default. This prevents public pages from selecting a hotel.
- `hotel.integrations` and `hotel.system-settings` remain global because their
  database tables are not tenant-scoped. Integration ownership and cache
  propagation belong to SAAS-04/05/API context work.
- Operational controllers and APIs remain unfiltered. Adding ad-hoc property
  predicates here would create unsafe partial isolation and is deferred.

## Phase SAAS-04: operational data ownership

SAAS-04 adds ownership metadata and database support for the existing shared
operational schema. It is an ownership foundation, not the query-isolation
phase. The current shared database still requires SAAS-05 before any customer
tenant can be treated as isolated.

### Ownership matrix

| Data group | Owner | Ownership source / notes |
| --- | --- | --- |
| Departments, floors, room categories, room types, amenities, rooms | Property | Direct `property_id`; room catalog records are assigned from the active property context. |
| Reservations, room blocks, payments, invoices | Property | Direct `property_id`; children are reconciled through their room, reservation, or payment parent where available. |
| Housekeeping and maintenance tasks | Property | Direct `property_id`; room ownership is the preferred inference source. |
| Clients / guests | Organization | Direct `organization_id`; the same guest may be used by multiple properties in one organization. |
| Financial accounts, expense categories, transactions, expenses, transfers, reconciliations, cash closes, payment refunds | Property | Direct `property_id`; account/transaction and account/transfer relationships are reconciled and validated. |
| POS outlets, categories, products, shifts, orders, room charges, audits | Property | Direct `property_id`; outlet, shift, reservation, room, and product relationships are validated on write. |
| Tasks and task tags | Property | Direct `property_id`; task child records continue to inherit ownership through the task parent. |
| Announcements | Organization, optional property | Organization ownership is required for normal use; `property_id` remains nullable for organization-wide announcements. |
| Notifications | Organization, optional property | Organization is inferred from the notifiable user where possible; property is retained as nullable until event sources are audited. |
| Channel connections and external reservations | Property | Connection ownership is direct; external reservations infer from connection/reservation and remain unresolved when sources disagree. |
| Email delivery and gateway logs | Nullable property | Inferred from reservation/payment when a source exists; platform-level or ambiguous logs remain nullable and are reported. |
| Website CMS, public enquiries, plans/subscriptions, RBAC and global reference data | Platform global | Deliberately not property-owned in this phase. |

Derived children such as POS order items/payments/refunds, task comments,
attachments, subtasks, activity, time entries, announcement recipients and
attachments continue to derive ownership through their parent. Duplicating
tenant IDs on those rows would create another consistency surface without
improving the current migration safety.

### Schema changes

The following migrations add nullable foreign keys, tenant-aware indexes and
the supporting lookup indexes:

- `2026_10_02_000010_add_operational_ownership_columns.php`
- `2026_10_02_000011_add_finance_ownership_columns.php`
- `2026_10_02_000012_add_pos_ownership_columns.php`
- `2026_10_02_000013_add_tasks_announcements_integration_ownership.php`

Property-local names, codes, room numbers, invoice numbers, task numbers,
POS SKUs and POS order identifiers now use composite unique indexes with
`property_id`. Generated sequence numbers remain globally unique for support
and reconciliation because the existing sequence allocators are global and
changing them would be a separate identifier migration.

The new columns are intentionally nullable during rollout. They must not be
made `NOT NULL` until a production-sized reconciliation proves that mandatory
roots have no null or orphan ownership and all legacy writers have been
updated. This repository does not include Doctrine DBAL, and SQLite table
rebuilds would make a blanket hardening migration unsafe at this stage.

### Backfill and reconciliation

`OperationalTenantBackfillService` is the single central backfill path. It:

1. selects the first active organization-backed property as the deterministic
   legacy property;
2. backfills direct organization/property owners without deleting rows;
3. infers child ownership from related parents where the relationship is
   unambiguous;
4. records relationship conflicts as anomalies and leaves those rows
   unresolved rather than repairing relationships silently; and
5. reports row counts and financial totals before and after the run.

Use the command only against a reviewed database or a disposable validation
database:

```text
php artisan saas:backfill-operational-ownership --dry-run
php artisan saas:backfill-operational-ownership
```

The command exits unsuccessfully when anomalies are present. Re-running it is
idempotent: owned rows are counted as existing, null rows are candidates for
backfill, and no operational rows are deleted. The central service is called
after the legacy reference seeders so model events being disabled during the
legacy seed process cannot leave the default reference catalog unowned.

### Write-path safeguards

`AssignsTenantOwnership` assigns ownership server-side on model creation when
an active `TenantContext` exists. Tenant IDs are not accepted from browser
forms. Legacy pre-context factories remain compatible while ownership columns
are nullable; authenticated request paths must resolve a context before
creating tenant-owned records.

`TenantOwnershipConsistencyService` is used by reservation, POS, finance and
task services to reject high-risk cross-property writes. Specifically:

- reservations require the guest organization and room property to agree;
- POS checkout validates outlet, shift, product, room, reservation and the
  idempotency key property before creating a sale or room charge;
- finance posting validates account, source, transaction and transfer
  properties, and refuses transfers across properties; and
- tasks validate room, reservation, housekeeping, maintenance and guest
  references before create/update. A legacy task import may retain a nullable
  unowned reference only when no tenant property context is resolved; once a
  property context exists, the same reference is rejected.

This is deliberately not a global Eloquent scope. Read isolation and complete
write-path auditing remain SAAS-05 work.

### Audit boundaries for SAAS-05

- Raw SQL remains in sequence allocators, backfill/reconciliation logic,
  reports, backup/restore support, and some legacy lookup paths. These paths
  need an explicit organization/property predicate or a platform-global
  classification before customer production.
- Route-model binding and operational controllers still resolve by primary key
  without a tenant scope. Middleware context exists, but it does not yet
  constrain every read.
- API token ownership, queued jobs/events, scheduler tasks, cache keys and
  file/storage paths need context propagation and namespace review.
- Website CMS, public enquiries and public routes intentionally stay outside
  hotel tenant context; they are platform-global and must not be used to infer
  operational ownership.
- Property switching remains a validated service foundation only and no normal
  switcher is enabled.

SAAS-04 therefore remains **NOT SAFE FOR MULTI-TENANT CUSTOMER PRODUCTION**.
SAAS-05 must complete read isolation, route authorization, background-job
context, API boundaries, cache/storage namespacing, null-owner remediation,
and production migration hardening before launch.

## Phase SAAS-05: isolation and access enforcement

SAAS-05 adds the first defense-in-depth read boundary over the SAAS-04
ownership metadata. The request-scoped `TenantContext` remains the source of
the active organization/property; the following layers now enforce it:

- tenant-owned Eloquent models use `ScopesTenantOwnership` for normal reads
  and expose explicit `forOrganization`, `forProperty`, and
  `forCurrentTenant` scopes;
- implicit route model binding therefore returns a 404 for an object outside
  the active property/organization, while service code can deliberately load
  a referenced object without the global scope and return a domain ownership
  error;
- `Gate::before` rejects a model resource that is outside the current tenant
  before any hotel super-administrator permission bypass is considered;
- API tokens carry organization/property ownership and middleware establishes
  that context before API controllers run; credential lookup is intentionally
  the one pre-context bypass and is followed by tenant activation;
- high-risk request validation uses tenant-aware `exists`/`unique` rules,
  while reservation, POS, finance and task services retain explicit ownership
  assertions for defense in depth;
- search, exports, dashboard queries and normal Eloquent aggregates inherit
  the model scope; raw SQL reference lookups in finance/POS are explicitly
  constrained, while global sequence allocators and migration/backfill,
  backup, health and setup tooling remain documented platform/console
  bypasses;
- tenant cache keys include organization/property identity; queued webhook,
  email and channel work activates the serialized ownership context before
  tenant-owned writes; scheduled announcements iterate active properties;
- notification center and the authenticated topbar filter database
  notifications by organization and current property, and database
  notifications created in a tenant context receive those ownership columns;
- integration settings, webhook endpoints/deliveries, integration logs and
  API tokens now have tenant ownership metadata and indexes.

The public website/CMS, public enquiries, login, password/2FA flows, global
RBAC/reference data and subscription skeleton remain intentionally outside
hotel tenant context. Property and organization switching UI remains disabled;
the context service is available to trusted request, API and worker paths
only. Mandatory operational and organization ownership columns are now
hardened by SAAS-05.1 after NULL precondition checks; explicitly documented
organization-wide audit records may retain nullable property ownership.

The complete validation record is in
`docs/SAAS-05-ISOLATION-VALIDATION.md`. SAAS-05 does not authorize customer
production onboarding until the disposable MariaDB run, isolation matrix and
remaining application tests are green.

## Phase SAAS-05.1: ownership hardening and staging baseline

SAAS-05.1 converts the mandatory SAAS-04 ownership columns from rollout
nullable to enforced `NOT NULL` columns after a precondition check. The full
ownership matrix, nullable-property classifications, runtime rules and test
coverage are recorded in `docs/SAAS-05-1-HARDENING.md`.

Run the default organization/property and operational backfills first, review
their anomaly and financial-total reports, then run migrations 000015 through
000021. The migrations intentionally fail rather than guessing when a
mandatory owner is still NULL. They must be run with normal `php artisan
migrate`; `migrate:fresh`, `db:wipe` and rollback commands remain blocked on
operational databases.

The only permitted disposable destructive validation is a newly created
database whose exact name begins with `reservations_pos_integration_` and is
not `reservations_db`, with `POS_MYSQL_INTEGRATION=true`. Record the migration,
seed/backfill, row-count, orphan, NULL and financial-total checks, then drop and
verify only that exact database. Do not use a local or Railway operational
database for this test.

SAAS-05.1 keeps property switching disabled and does not authorize SAAS-06,
billing, signup or platform-administration UI work.

## SAAS-06 multi-property UX

SAAS-06 adds a session-based authenticated property/organization switcher and organization-scoped property management. Enable `HOTEL_MULTI_PROPERTY_UI=true` for controlled staging. Context mutations are POST-only and CSRF-protected; tenant ownership and property access remain server-validated. No billing, signup, entitlement, or Platform Admin behavior is included. See `docs/SAAS-06-MULTI-PROPERTY-UX.md` for redirect, session cleanup, multi-tab, and validation rules.

End of the SAAS-06 migration notes.
