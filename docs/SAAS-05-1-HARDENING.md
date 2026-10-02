# SAAS-05.1 tenant ownership hardening

This phase hardens the SAAS-04 ownership metadata after the SAAS-05 isolation
work. It does not enable property switching, customer onboarding, billing UI,
or SAAS-06 work. The public website/CMS remains platform-global.

## Ownership policy

Operational records require a property: departments, floors, room categories,
room types, amenities, rooms, reservations, room blocks, housekeeping tasks,
maintenance tasks, finance accounts/categories/transactions/expenses/transfers/
reconciliations/cash closes/refunds, POS outlets/categories/products/shifts/
orders/room charges/audits, tasks, task tags, channel connections and external
reservations.

Organization ownership is mandatory for clients, announcements, notifications,
integration settings, webhook endpoints, API tokens, integration logs, webhook
deliveries and inbound webhook events. `properties.organization_id` is also
mandatory.

Nullable property ownership is deliberate for organization-wide announcements,
notifications and inbound webhook events. Integration logs and webhook
deliveries may also be organization-wide audit records. Email and gateway
delivery logs remain nullable because platform-level and ambiguous records are
valid; relationship-derived ownership is applied when it is unambiguous.
Child records inherit ownership through their tenant-owned parent.

Website CMS, public enquiries, plans/subscriptions, RBAC, currencies,
languages and system settings are platform-global. Property settings are
property-scoped. Exports are streamed and do not create an unowned persisted
export record.

## Runtime rules

`AssignsTenantOwnership` no longer selects the first property or organization
at runtime. Authenticated HTTP requests resolve context through membership and
property access; API tokens activate their stored trusted context; jobs carry
serialized ownership and activate it before writing; imports require an active
property; and inbound webhooks identify the tenant by a verified provider
signature before inserting an event. Browser payloads cannot choose ownership.

The only automatic tenant bootstrap is `TestingTenantBootstrap`, reachable
only while the application is running tests. Setup and seeders use explicit
organization/property creation and activation. Property ownership is immutable,
and model fillable lists do not expose tenant columns.

## Migration sequence

The nullable rollout columns are introduced by migrations 000010–000014. The
hardening sequence is separate and refuses to proceed when unresolved NULL
ownership exists:

1. `000015_harden_core_tenant_ownership`
2. `000016_harden_finance_tenant_ownership`
3. `000017_harden_pos_tenant_ownership`
4. `000018_harden_task_channel_ownership`
5. `000019_harden_organization_ownership`
6. `000020_harden_api_token_property_ownership`
7. `000021_add_webhook_inbound_tenant_ownership`

Each mandatory-column migration checks for NULLs before changing nullability,
rebuilds the foreign-key constraint with restrictive behavior, and supports
SQLite and MySQL/MariaDB. Run the default and operational backfills before the
hardening migrations on a legacy installation. No destructive database command
is part of the production migration path.

## Validation coverage

The automated suite covers mandatory ownership columns and NULL-creation
rejection, normal server-side assignment, attempted tenant tampering,
cross-property references in reservations/tasks/finance/POS, CSV imports,
API-token and notification ownership, jobs, webhooks, idempotent backfill and
financial-total reconciliation. Public routes remain outside tenant context
and authenticated routes remain protected.

`tests/Feature/SaasHardeningTest.php` is the schema/runtime matrix. The SAAS
foundation, context, isolation, operational, integration, API, people and
transfer tests cover the broader paths.

## Staging gate

SAAS-05.1 is not customer-production approval. Before controlled staging,
record the exact disposable MariaDB database name, migration result, two
idempotent backfill reports, NULL/orphan checks and financial-total comparison.
Drop only that verified disposable database after validation. Keep property
switching disabled until a later phase explicitly completes the remaining
customer-facing tenancy workflow.
