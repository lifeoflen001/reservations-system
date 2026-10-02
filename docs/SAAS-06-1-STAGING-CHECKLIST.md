# SAAS-06.1 controlled staging checklist

This checklist is for a disposable or controlled staging environment only. It
does not authorize production deployment, billing, signup, or Platform Admin.

## Fixture

Create only synthetic records:

- Organization Alpha
  - Alpha 1
  - Alpha 2
- Organization Beta
  - Beta 1
- Alpha Owner
- Alpha Restricted User
- Beta Owner

Assign explicit organization memberships and property access. Do not use real
guest, staff, email, payment, or credential data.

## Tenant and access checks

- [ ] Alpha Owner can switch Alpha 1 and Alpha 2.
- [ ] Alpha Restricted User can see only the assigned Alpha property.
- [ ] Beta Owner cannot see Alpha organizations, members, properties, or audit.
- [ ] Cross-organization property assignment is rejected server-side.
- [ ] Revoked property access disappears on the next request.
- [ ] Inactive membership loses organization access on the next request.
- [ ] A property switch clears property-sensitive session state.
- [ ] Multi-tab behavior is understood: context is session-wide and the topbar
      always identifies the active property.

## Organization administration

- [ ] Organization settings show the current organization scope.
- [ ] Organization name, billing email, country, timezone, and default
      currency update without changing property operational data.
- [ ] Property management lists active and inactive properties for the current
      organization only.
- [ ] Property creation grants creator access and starts with no operational
      records.
- [ ] Property access can be managed from both the member and property views.
- [ ] Staff remains the property-scoped employee screen; members remain account
      access records.

## Audit

- [ ] Organization settings updates create audit records.
- [ ] Property creation and updates create audit records.
- [ ] Membership role/status changes create audit records.
- [ ] Property access grants and revocations create audit records.
- [ ] Successful organization/property switches create audit records.
- [ ] Audit records are paginated, organization-scoped, and have no edit/delete
      action.

## Operational walkthrough

For Alpha 1 and Alpha 2, verify the active context in:

- [ ] Dashboard
- [ ] Reservations
- [ ] Rooms
- [ ] Room planning
- [ ] Clients
- [ ] Tasks
- [ ] Housekeeping
- [ ] Maintenance
- [ ] POS
- [ ] Finance
- [ ] Reports
- [ ] Notifications
- [ ] Search
- [ ] Settings

## Platform and infrastructure

- [ ] APP_ENV is staging and APP_DEBUG is false.
- [ ] Staging database and safe/sandbox mail configuration are used.
- [ ] Queue worker and scheduler use staging configuration.
- [ ] Tenant-sensitive files use staging storage, not local development paths.
- [ ] Logs contain useful tenant context without passwords, tokens, or financial
      secrets.
- [ ] A staging backup is created and restored to a disposable database.
- [ ] No destructive database command is used against operational data.

## Browser QA

- [ ] Desktop: 1920, 1440, 1366, 1280.
- [ ] Tablet: 1024, 768.
- [ ] Mobile: 430, 390, 360.
- [ ] Switcher, organization settings, properties, members/access, audit, and
      room planning have no horizontal overflow.
- [ ] Dark mode is checked on all customer-side administration screens.
- [ ] Labels, focus states, tables, checkboxes, and forms are keyboard usable.

## Release boundary

SAAS-06.1 does not add billing, subscription controls, invitations, public
signup, platform audit, support impersonation, or Platform Admin. Those remain
SAAS-07+ work.
