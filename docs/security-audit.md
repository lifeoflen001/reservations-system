# Lodgix security audit

## Scope and evidence

- Repository: `reservations-system-fresh`
- Reviewed state: current worktree based on `72a217a`
- Application: Laravel 12 / PHP 8.2 hotel-management SaaS with organization and property tenancy
- Baseline scanner: Codex Security prompt-driven standard scan `1a6756b5-8cb9-4cbe-b0c8-bb280778d89c` was started against the full repository. Its result is not treated as complete until the scanner publishes a completed report.
- Complementary checks: Composer audit, npm audit, focused Laravel feature tests, PHP syntax checks, route/config/view cache commands, and source review of routes, middleware, policies, tenant scopes, upload paths, integrations, secrets, and logs.

No credentials or secret values are included in this document.

## Findings

### SEC-001 — Payment gateway callback was not tenant-bound

- **Affected component:** `app/Services/GatewayCallbackService.php`, gateway webhook route, `gateway_transactions` schema.
- **Vulnerability/missing control:** The callback previously loaded one gateway integration without resolving the tenant from the signed request. It also performed reservation lookup and payment posting without activating the signed property's tenant context. Provider/external transaction uniqueness was global instead of property-scoped.
- **Severity:** High before remediation.
- **Exploitation conditions:** An attacker needs a valid gateway signature for an enabled integration. The old code path then lacked a reliable property context, allowing a crafted signed payload containing another reservation ID to reach unscoped reservation/payment logic, or causing cross-tenant event collisions.
- **Potential impact:** Payment records could be posted against the wrong tenant's reservation, or a valid event could be rejected/collide when two properties used the same provider event ID.
- **Remediation:** Resolve exactly one enabled integration by verifying the raw body signature across tenant-owned settings; require an active organization and property; activate that context before reservation lookup and transaction creation; reject reservations outside the context; enforce idempotency payload consistency; scope uniqueness by property.
- **Test evidence:** `tests/Feature/Phase09IntegrationsTest.php::test_gateway_callbacks_resolve_the_signed_property_and_keep_event_ids_tenant_scoped` passed. Existing signed webhook and SaaS isolation tests also passed.
- **Resolution status:** Fixed and verified locally; deployment verification remains pending.

### SEC-002 — CommonMark dependency advisories

- **Affected component:** `league/commonmark` in Composer lockfile.
- **Vulnerability/missing control:** Composer audit reported one high and one medium advisory affecting versions through 2.10.1.
- **Severity:** High/medium dependency advisories, dependent on whether attacker-controlled Markdown reaches the parser and which extensions are enabled.
- **Exploitation conditions:** A reachable Markdown rendering path must process attacker-controlled content under an affected version.
- **Potential impact:** Parser denial of service or raw-HTML policy bypass in an applicable rendering path.
- **Remediation:** Updated `league/commonmark` from 2.10.1 to 2.10.3, with its compatible transitive polyfill update. Re-run `composer audit` in CI and after every lockfile change.
- **Test evidence:** Composer update completed the lockfile update; the final Composer audit reported no advisories and the full Laravel regression suite passed with 213 tests and 1,647 assertions.
- **Resolution status:** Patched and verified locally; deployment verification remains pending.

## Controls reviewed

| Surface | Result | Evidence/status |
|---|---|---|
| Admin routes and platform control plane | Protected by authenticated guards, platform 2FA, permission middleware, and controller/policy checks | Verified by route/source review and existing platform tests |
| Tenant and property isolation | Global tenant scopes, active context resolution, policy checks, tenant-aware validation, and isolation tests | Verified; gateway callback gap fixed |
| Browser sessions | Database-backed server sessions, HttpOnly/SameSite defaults, regeneration on login/logout, configurable inactivity timeout | Production Secure flag now defaults on; proxy trust must be configured |
| API credentials | SHA-256 token lookup, one-time display, revocation/expiry, ability checks, tenant activation | Verified by integration tests |
| CSRF | Laravel web CSRF remains enabled; only signed webhook paths are excluded | Verified by bootstrap/routes review |
| Webhooks | Timestamped HMAC signatures, exact tenant matching, idempotency records, gateway payload consistency | Provider webhook verified; gateway path remediated |
| Uploads | Image/MIME/size/dimension validation and authorized task downloads | Verified by source review; production storage headers still need deployment verification |
| Secrets | Integration secrets encrypted at rest; API token hashes and hidden model fields | Verified by source/tests |
| Headers | `nosniff`, frame protection, referrer and permissions policies, CSP report-only, production HSTS | Implemented; CSP enforcement requires compatibility verification |
| CORS | No permissive CORS middleware/config is enabled | Verified; keep same-origin by default |
| Logging | No password/token logging found in reviewed application paths; audit events use IDs and non-sensitive metadata | Verified by source review; central log retention/redaction remains operational work |
| CAPTCHA | No existing centralized provider integration found | Open/blocked pending provider credentials, policy, privacy copy, and acceptance tests |

## Residual risks and blockers

1. Configure `TRUSTED_PROXIES` with Railway's actual trusted proxy range or platform-supported value. Do not restore a wildcard default.
2. Verify CSP report-only violations in staging, then set `SECURITY_CSP_ENFORCE=true` after legitimate integrations are allowlisted.
3. Configure `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` (or rely on the production default), HTTPS, database-backed/Redis sessions, and restricted database credentials in production.
4. CAPTCHA has not been enabled or wired into forms because the repository has no provider credentials, hostname/action policy, privacy notice, or deterministic test strategy. Enabling it without those inputs would risk silently bypassable or broken authentication/public forms.
5. Re-run the Codex Security scan to completion and record its sealed report before declaring the repository security audit complete.

