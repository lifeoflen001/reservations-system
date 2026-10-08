# Lodgix production security checklist

## Application

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_KEY` is present, managed, and not committed
- [ ] `TRUSTED_PROXIES` contains only the real reverse-proxy network
- [ ] `SESSION_SECURE_COOKIE=true` and `SESSION_HTTP_ONLY=true`
- [ ] `SESSION_SAME_SITE=lax` or a reviewed stricter value
- [ ] HTTPS is enforced and verified through the real proxy
- [ ] `SECURITY_CSP_ENFORCE=true` after staging report-only review
- [ ] `php artisan config:cache`, `route:cache`, and `view:cache` succeed

## Data and tenancy

- [ ] MySQL credentials use a least-privilege application account
- [ ] Tenant uniqueness migrations have run successfully
- [ ] Database backups, restore tests, and retention are configured
- [ ] Redis is configured for shared cache/session/queue use when horizontally scaled
- [ ] Live reservation availability, payments, balances, and check-in state remain database-sourced

## Integrations

- [ ] Gateway/provider secrets are encrypted and rotated through authorized settings
- [ ] Webhook timestamps/signatures and idempotency are tested against each provider
- [ ] Email, WhatsApp, OTA, and webhook work is queue-backed where appropriate
- [ ] CAPTCHA is configured only after the documented provider contract and tests exist

## Operations

- [ ] Logs exclude passwords, bearer tokens, cookies, secrets, and unnecessary guest data
- [ ] Central logs have access control, retention, alerting, and redaction
- [ ] Composer and npm audits run in CI
- [ ] Static assets use immutable cache headers; HTML and authenticated/API responses are not shared cached
- [ ] DNS, CDN, Brotli/gzip, HTTP/2 or HTTP/3, TLS certificates, and origin firewall rules are verified at the infrastructure layer

