# Database safety and migration policy

## Normal schema changes

For an existing installation, apply schema changes incrementally:

```bash
php artisan migrate
```

Deployment may use `php artisan migrate --force` when the deployment process is non-interactive. Do not replace this with `migrate:fresh`.

## Destructive commands

`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, and `schema:drop` can remove application data or schema. They are blocked by the application unless all of the following are true:

- the application is running in the isolated PHPUnit environment (`APP_ENV=testing`);
- the database driver is SQLite; and
- the database is the in-memory database (`DB_DATABASE=:memory:`).

`migrate:fresh --seed` is destructive and must only be used on disposable/test databases. Never run it against a development, staging, production-like, or production database.

An explicit `DB_ALLOW_DESTRUCTIVE_COMMANDS=true` override is accepted only for a deliberately approved disposable SQLite database whose name clearly identifies it as test, temporary, or audit data. It is still rejected for MySQL, staging/production environments, and `reservations_db`. Set it temporarily, verify the connection first, and remove it immediately afterward.

## Testing database

`.env.testing` and `phpunit.xml` force tests to use `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:`. `Tests\\TestCase` fails fast if those settings change, so the test suite cannot silently connect to `reservations_db`.

## Seeders

RBAC synchronization uses syncWithoutDetaching to preserve legitimate custom access. To retire an application-owned permission, add its exact name to hotel.retired_permissions; the seeder detaches it only from system roles and deletes it only when no role still uses it. Custom-role assignments are preserved for administrator review.

## POS accounting

A completed POS sale is recognized once as POS revenue. A room charge adds to the reservation folio and remains part of the amount due. Later PMS payments reduce the balance, but revenue reporting allocates payments to the reservation's accommodation total first; payment amounts that settle a POS room charge are therefore not added as a second revenue event. The payment remains visible in payments, invoices, and the folio.

The application seeders are intended to be repeatable reference/bootstrap seeders. They create missing defaults without deleting records, overwriting existing reference values, replacing existing role assignments, or changing an existing installation state. An explicitly supplied `ADMIN_RESET_PASSWORD` remains an intentional administrative override.
