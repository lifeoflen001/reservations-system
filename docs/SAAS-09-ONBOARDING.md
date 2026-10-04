# SAAS-09 — Customer onboarding and invitations

SAAS-09 adds the customer acquisition path without adding billing or trial-lifecycle semantics.

## Registration and verification

`/register` creates only the user account. Registration is rate-limited, uses the existing password policy, requires explicit terms acceptance, normalizes the email address, and marks the account as requiring verification. Laravel's signed verification URL is used; no custom raw verification token is stored. Until verified, the account is limited to verification, resend, logout, and the invitation path.

## Provisioning

`OrganizationProvisioningService` creates the organization, explicit owner membership, persistent `OrganizationOnboarding` state, and onboarding audit rows in one transaction. Organization names and property slugs are generated server-side. The owner is assigned the existing Administrator role template and cannot submit an organization identifier from the browser.

`SubscriptionBootstrapService` is the only onboarding subscription creation boundary. It accepts only active, public, non-system plans marked onboarding-eligible. It uses `trialing` as the initial access state without a trial deadline; trial expiry and lifecycle states remain SAAS-10 work. The internal Legacy Full Access plan is never selectable during public signup.

`OnboardingService` persists the required sequence: organization, plan, first property, and hotel-ready completion. Each POST is idempotent for the owner, validates server-side state, applies `UsageLimitService`, grants the owner property access, activates `TenantContext`, and redirects to the real dashboard with existing empty states.

## Invitations

`organization_invitations` stores only a SHA-256 token hash. Invitation URLs contain the raw token only at delivery time. Invitations are pending, accepted, expired, or revoked; expiry is evaluated dynamically and defaults to the configured seven-day period. Resend revokes the previous record and issues a new token. Acceptance is transactional, matches the authenticated email, validates the organization role and captured properties, rechecks the active-user limit, creates only a non-owner membership, and cannot reuse an accepted/revoked/expired token.

Members with the existing `members.view` and `members.manage` permissions can review, send, resend, and revoke invitations at `/settings/invitations`. Platform administrators remain a separate identity and are never created by customer registration.

## Limits and security

Pending invitations do not consume active-user capacity; capacity is checked again at acceptance. First-property creation uses the same property entitlement limit as normal property creation. Foreign organization/property identifiers are not trusted. State-changing forms use CSRF protection, and the registration honeypot provides a provider-independent bot-control hook.

## Boundaries

SAAS-09 does not add payment capture, invoices, refunds, recurring billing, trial expiration, grace, past-due, cancellation, or public pricing synchronization. Railway staging certification remains pending and is a release gate rather than a local development gate.
