# Lodgix release checklist

Use this checklist for an authorized Railway release. It does not authorize a
deployment by itself.

## Repository

- [ ] Intended commit and diff reviewed.
- [ ] No secrets, `.env`, local databases, `artifacts/`, `pull/`, `push/`, or
      unrelated working-tree edits are in the release.
- [ ] `git diff --check` passes.

## Tests and build

- [ ] `composer validate` reviewed.
- [ ] `npm run build` passes.
- [ ] `php artisan config:cache`, `route:cache`, and `view:cache` pass.
- [ ] Public route tests pass.
- [ ] Finance/POS tests pass.
- [x] Full suite is green: 119 tests, 913 assertions.
- [x] Rooms pagination rows filter is restored with the shared component.
- [x] Profile upload test uses a self-contained fake image fixture.

## Database

- [ ] Railway MySQL identity and credentials verified.
- [ ] `php artisan migrate:status` shows the expected state.
- [ ] Backup completed and restoration has been tested in a disposable target.
- [ ] Pre-deploy uses only incremental `php artisan migrate --force`.
- [ ] `RbacSeeder` is the only automatic reference seeder.

## Environment

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, stable `APP_KEY`, and final HTTPS
      `APP_URL` are configured in Railway secrets.
- [ ] `DB_ALLOW_DESTRUCTIVE_COMMANDS=false`.
- [ ] `HOTEL_SETUP_ENABLED=false` after initial administrator provisioning.
- [ ] No production value is stored in Git.

## Storage

- [ ] Railway persistent volume is attached and its actual mount path is
      documented, or an approved S3-compatible store is configured.
- [ ] `php artisan storage:link` is verified.
- [ ] A disposable upload survives a service restart and can be removed.
- [ ] User files and database backups are included in separate backup plans.

## Queue and scheduler

- [ ] Worker runs `php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600`.
- [ ] `jobs` and `failed_jobs` tables exist.
- [ ] One harmless queued action succeeds.
- [ ] Scheduler runs `php artisan schedule:run` every minute.
- [ ] `announcements:sync` executes once without duplicate scheduler services.

## Mail

- [ ] SMTP/provider credentials are configured as secrets.
- [ ] Sender domain is verified.
- [ ] `MAIL_SCHEME` and `MAIL_TIMEOUT` are correct.
- [ ] One controlled staging message succeeds.
- [ ] Provider outage produces a safe queued failure without secret leakage.

## Domain and security

- [ ] Railway domain works before custom DNS is changed.
- [ ] Custom domain has active HTTPS.
- [ ] Proxy forwarding preserves secure URLs, redirects, and cookies.
- [ ] Security headers are present on public, login, dashboard, Finance, and
      POS responses.
- [ ] `/up` is liveness-only and `/api/health` exposes safe readiness data.
- [ ] CSP remains deferred unless separately audited and approved.

## Public site and authentication

- [ ] `/`, `/product`, `/operations`, `/pos`, `/finance`, `/security`, and
      `/integrations` render without operational data.
- [ ] `/sitemap.xml` and `/robots.txt` contain only intended public routes.
- [ ] `/login`, logout, invalid login, protected-route redirect, and dashboard
      access are verified with non-production users.
- [ ] `/pos/terminal` and `/finance/overview` remain authenticated routes.

## Finance and POS

- [ ] Reservation, payment, invoice, folio, POS, and report smoke checks pass.
- [ ] Room-charge revenue is not double-counted when later settled.
- [ ] Failed/duplicate provider callbacks remain idempotent.
- [ ] No real funds or production guest data are used for release testing.

## Monitoring and rollback

- [ ] Railway web, worker, scheduler, database, storage, mail, and webhook logs
      are observable.
- [ ] Failed jobs and application errors have an operator owner.
- [ ] Release identifier and backup location are recorded.
- [ ] Rollback decision tree in `HOSTING.md` has been reviewed.
