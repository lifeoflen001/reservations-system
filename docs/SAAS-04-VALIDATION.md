# SAAS-04 validation record

This record covers the disposable validation for operational ownership. It is
not a production migration approval and it does not certify tenant isolation.

## Schema and backfill checks

- SQLite fresh migration: passed before the SAAS-04 feature suite.
- MySQL/MariaDB disposable migration: recorded below after the validation run.
- `saas:backfill-operational-ownership --dry-run`: must complete without
  anomalies on the disposable fixture.
- Actual backfill followed by a second actual backfill: the second run must
  report no newly backfilled rows.
- Financial totals before and after ownership assignment: must be identical.
- Disposable database: must be removed after validation.

## Ownership acceptance coverage

`tests/Feature/SaasOperationalOwnershipTest.php` covers:

- duplicate room numbers, finance account codes and POS outlet codes across
  properties;
- duplicate room numbers rejected within one property;
- one organization guest shared across two properties;
- cross-organization guest/reservation rejection;
- cross-property POS room-charge rejection;
- cross-property finance transfer/account rejection;
- cross-property task/room rejection; and
- idempotent backfill with unchanged financial totals.

## Disposable MySQL run

The exact database name, commands, counts, totals and teardown result are to be
recorded here from the run. No operational database may be used for this
validation.

```text
Database: reservations_pos_integration_saas04_20261002 (disposable)
Server/version: MariaDB 10.4.32, XAMPP, PHP PDO MySQL
Migration result: passed; migrations 000010 through 000013 completed
Dry-run result: passed; no anomalies
First backfill result: passed; no new rows required because the seeded fixture was already owned
Second backfill result: passed; idempotent, no new rows backfilled
Null mandatory ownership rows: 0 across the validated property-owned tables; 0 for clients, announcements and notifications organization ownership
Orphan ownership rows: 0 reported by the backfill command
Financial totals before/after: unchanged; all eight disposable-fixture totals were 0.00 before and after
MySQL feature acceptance: passed; 6 tests, 21 assertions
Database removed: confirmed; exact schema name no longer appears in INFORMATION_SCHEMA
```

## Readiness statement

Even with green disposable SQLite/MySQL validation, SAAS-04 is **NOT SAFE FOR
MULTI-TENANT CUSTOMER PRODUCTION**. Ownership columns are nullable, operational
reads are not globally scoped, route binding and API isolation are incomplete,
and job/event/cache/storage context still require SAAS-05. SAAS-05 has not
been started by this phase.
