# SAAS-08 — Plans, Features, Entitlements and Usage Limits

SAAS-08 adds the commercial capability layer without adding billing, checkout, pricing, or public signup.

## Model

`Organization` remains the tenant boundary. A current `Subscription` points to one `Plan`. A plan has many boolean `Feature` records through `plan_features` and measurable `PlanLimit` records through `plan_limits`.

Feature keys are stable machine-readable identifiers. System features are seeded only for modules that exist in Lodgix. A feature entitlement does not replace a user permission: effective access requires both the organization entitlement and the existing role/permission check.

Supported subscription states are the existing values: `trialing`, `active`, `past_due`, `grace_period`, `suspended`, and `cancelled`. SAAS-08 grants commercial feature access only for `trialing`, `active`, and `grace_period` subscriptions attached to an active plan. Full lifecycle behavior remains deferred to SAAS-10.

## Services

- `EntitlementService` resolves the current plan, subscription eligibility, features, limits, remaining capacity, and denial explanations. Its cache key includes organization, subscription, and plan update identity.
- `UsageService` measures non-archived properties, active organization memberships, and active rooms across the organization’s properties.
- `UsageLimitService` is the server-side gate for property, member, and room creation. Property creation locks the organization row before checking capacity.
- `feature:<key>` middleware enforces optional features on customer routes and the API. Navigation filtering is only a convenience; backend middleware is authoritative.

Storage and monthly API quotas are deliberately deferred because reliable tenant metering does not yet exist. `api_access` is enforced as a feature, but request quotas are not.

## Compatibility and downgrade behavior

The migration creates a private system plan named `Legacy Full Access` and attaches it to existing organizations/subscriptions that do not have a plan. This prevents legacy organizations from losing access during rollout and does not create a public commercial plan or price.

Downgrades never delete or deactivate operational data. Optional UI and direct route access become unavailable when the feature is removed. Reassigning a plan with the feature makes historical data available again. Existing resources over a newly reduced limit remain intact; only new creation is blocked.

## Platform and customer surfaces

Platform administrators manage plans at `/platform/plans` and inspect the stable feature catalog at `/platform/features`. Changes are protected by platform authentication, mandatory 2FA, dedicated platform permissions, and platform audit events. Customer organizations have a read-only `/settings/subscription` view. Public pricing remains independent and unchanged.

The read-only Platform Support workspace displays the selected organization’s effective features and hides/blocks POS, Finance, Reports, Room Planning, or other gated modules when the organization is not entitled. Support does not bypass plan access.

## Validation boundary

Railway staging acceptance remains outstanding. SAAS-08 is intended to be validated locally with SQLite, disposable MariaDB migrations, SaaS isolation, platform, Finance/POS, public/CMS, build, lint, and cache checks. No production deployment is part of this phase.
