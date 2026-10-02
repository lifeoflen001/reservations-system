# SAAS-06 — Multi-property UX

## Scope

SAAS-06 makes the validated SAAS-05 tenant context visible and switchable in the authenticated Lodgix application. It does not add billing, signup, trials, entitlements, or Platform Admin functionality.

## Context model

Tenant context remains session-wide. The authenticated topbar shows the active property and organization. The property list is built from the current user’s active organization membership and active property memberships; it is never populated from an untrusted browser list.

Switching uses CSRF-protected POST actions:

- `POST /context/property`
- `POST /context/organization`

The server validates the authenticated user, active membership, organization ownership, property status, and property access before updating the session preference.

## Redirects and cleanup

`TenantContextSwitchRedirector` preserves known list/index routes and maps resource-detail, edit, invoice, order, and expense URLs to safe module indexes. External `return_to` values are rejected. Switching clears temporary property-sensitive session values including POS cart/outlet/shift, finance selections, report/task/reservation filters, search state, and recent IDs.

The context remains session-wide by design. A switch in one browser tab affects the next request in every tab in the same authenticated session. Each response renders the active property in the topbar so stale tabs are visibly identifiable.

## Property management

`/settings/properties` is organization-scoped and permission-gated. It lists only properties in the current organization and allows authorized users to create or update permitted identity fields. New properties receive creator access and start with no copied rooms, reservations, finance, POS, or task data. Commercial plan limits are deferred.

## UI and accessibility

The switcher is compact on desktop, hides secondary organization text on smaller widths, and becomes a full-width touch-friendly menu on mobile. It supports keyboard focus, Enter/Space activation through native buttons, Escape via the existing dropdown handler, ARIA expanded state, and property search without a client-side tenant store. It uses existing Lodgix tokens and dark-mode variables.

## Configuration

`HOTEL_MULTI_PROPERTY_UI` controls the authenticated switcher and property-management release. It defaults to enabled for controlled staging. It does not bypass tenant middleware or alter public website/CMS routes.

## Validation target

The phase requires SQLite regression coverage plus a disposable MariaDB multi-property run covering two properties in organization A, one property in organization B, authorized and unauthorized switching, session cleanup, and operational isolation. The database must be removed after validation. Production rollout remains separate from this checkpoint.

## SAAS-06.1 administration follow-up

SAAS-06.1 adds customer-side organization administration without introducing
Platform Admin, billing, signup, invitations, or subscription controls:

- /settings/organization edits profile-level organization information only;
- /settings/members manages existing organization memberships and explicit
  property access;
- /settings/properties/{property}/access provides the property-centric view
  of the same access records;
- /settings/audit shows immutable, organization-scoped administration history.

MembershipAccessService is the single mutation path for role/status/property
access changes. It validates organization consistency, preserves revoked rows,
clears the acting user's sensitive session state, and records audit metadata.
There is no formal organization-owner field in the current schema, so the
phase prevents self-deactivation and removal of the last active membership but
does not invent a separate owner role. Invitations remain deferred.

The controlled staging fixture and browser QA checklist is in
docs/SAAS-06-1-STAGING-CHECKLIST.md. The room-planning failure was reviewed:
the query's room-type filter is property-scoped and correct; the intermittent
failure came from a broad room-number assertion matching incidental HTML text.
The assertion now checks the rendered room-label markup, and the full SQLite
suite passes.
