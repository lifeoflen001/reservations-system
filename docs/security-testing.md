# Lodgix security testing

## Commands run

- `php artisan test tests/Feature/Phase09IntegrationsTest.php tests/Feature/SecurityHardeningTest.php tests/Feature/SaasIsolationTest.php`
- `php artisan test tests/Feature/Phase09IntegrationsTest.php tests/Feature/SecurityHardeningTest.php`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan config:clear`
- `php -l` on each changed PHP source/migration file
- `composer audit --format=json`
- `npm audit --omit=dev --json`

## Results

- Gateway/integration/isolation/header focused suite: **8 passed, 46 assertions** on the first run after the fix (the standalone header test was corrected from a redirected unseeded `/login` request to the public home route).
- Final focused suite: **6 passed, 39 assertions**.
- Final full suite: **213 passed, 1,647 assertions** in 239.31 seconds.
- Existing performance/error working-tree changes were preserved; the previously recorded full baseline was **211 tests, 1,635 assertions** before this security pass.
- Config, route, and view cache commands completed successfully.
- PHP syntax checks completed successfully.
- npm audit reported no vulnerabilities.
- Composer audit initially reported `league/commonmark` advisories for 2.10.1; the dependency was updated to 2.10.3 and the final Composer audit reported no advisories.

## Security scenarios verified

- Signed gateway callback resolves the matching property when two tenants share a provider and external event ID.
- Tenant B records are not visible through Tenant A's normal Eloquent queries or route bindings.
- API token access remains hashed, scoped, revocable, and tenant-bound.
- Signed inbound provider webhook processing remains idempotent.
- Public responses contain the expected browser security policy headers.

## Not yet verified

- Sealed Codex Security scan report: the scan was started and a threat-model context was available, but the scan worker had not published a completed report at the time of this document. The manual source-backed review and tests in this repository are the basis for the findings recorded in `docs/security-audit.md`.
- Railway proxy/header/cookie behavior and MySQL migration execution require deployment/staging verification.
- CSP should be promoted from report-only only after reviewing staging browser reports.
- CAPTCHA provider verification and form coverage are not implemented because the required provider configuration is absent.

