# Lodgix security hardening implemented

## Tenant and integration boundaries

- Signed gateway callbacks now verify the raw request body against every enabled tenant integration and require exactly one match.
- The matching organization/property is activated before reading reservations or writing gateway transactions.
- Reservation IDs supplied by a provider are resolved through the active tenant scope; cross-property IDs are rejected.
- Replayed gateway events must retain the same reservation and amount data.
- Gateway transaction event uniqueness is property/provider/external-ID scoped.
- Inbound provider-event uniqueness is organization/property/provider/event scoped.
- Integration and webhook secrets remain encrypted or hashed using the existing model casts/services.

## Browser and transport controls

- Forwarded headers are trusted only when `TRUSTED_PROXIES` is explicitly configured.
- Production session cookies default to Secure while local HTTP remains supported through the explicit local example setting.
- A CSP report-only policy covers self-hosted execution, framing, forms, objects, images, fonts, styles, scripts, and connections. It can be promoted with `SECURITY_CSP_ENFORCE=true` after staging verification.
- Production HTTPS responses receive HSTS only when Laravel sees the request as secure.
- Authenticated, platform, and API responses are marked private/no-store to avoid shared-cache leakage.
- Existing `nosniff`, frame, referrer, and permissions policies remain enabled.

## Existing controls preserved and verified

- Server-side route permissions and Laravel policies remain authoritative.
- Tenant global scopes and `TenantContext` remain the source of tenant filtering.
- API tokens remain hashed, scoped, revocable, and tenant-bound.
- Web application CSRF protection remains enabled; signed machine webhooks are handled separately.
- Upload endpoints retain content/type/size/dimension validation and authorized download checks.
- No UI redesign, SaaS capability removal, billing change, reservation workflow change, or production data mutation was introduced.

## Production configuration

Set these through the deployment secret/configuration system, never in Git:

- `APP_DEBUG=false`
- `APP_KEY` to a managed, rotated application key
- `TRUSTED_PROXIES` to the actual trusted proxy network
- `SESSION_SECURE_COOKIE=true` when HTTPS is used
- A restricted MySQL user with only required privileges
- Redis for shared cache/session/queue infrastructure where multiple app instances are used
- HTTPS, HSTS validation, secure DNS, and private log retention

