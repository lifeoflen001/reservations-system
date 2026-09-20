# Hotel PMS architecture foundation

## Repository audit

The repository is an uncustomized Laravel 12.69.2 application running on PHP 8.2+. It contained only the stock `users`, cache, and jobs migrations, the default `User` model, one `/` route returning `welcome.blade.php`, and the starter Tailwind/Vite assets. There was no authentication UI, domain schema, shared application shell, authorization layer, or hotel workflow implementation. No existing business code was replaced.

## Selected architecture

- Laravel MVC with Blade-rendered pages and small Alpine/JavaScript enhancements only where needed.
- Eloquent models represent the hotel domain; controllers should remain thin and call Form Requests, policies, and services.
- `RoomAvailabilityService` is the single availability boundary for overlapping reservations, explicit room blocks, and scheduled maintenance. Pending, confirmed, and checked-in reservations consume inventory; checked-out, cancelled, and no-show records do not. The overlap rule is `existing.check_in < requested.check_out && existing.check_out > requested.check_in`, so same-day turnover is allowed.
- Transactional workflow services own multi-record operations such as check-in, check-out, payments, and task completion.
- String-backed PHP enums define independent status domains without database `ENUM` constraints, so new states can be introduced safely.
- `config/hotel.php` and CSS custom properties centralize defaults and theming. Brand colors are separate from semantic status colors.

## Database entities and relationships

Reference data includes `currencies`, `languages`, and `reservation_sources`. Installation/property configuration is represented by `installations`, `properties`, and `system_settings`.

RBAC uses `departments`, `roles`, `permissions`, and the `permission_role` pivot. Users now support username, department, role, language, active state, and last-login tracking.

Room inventory is normalized across `floors`, `room_categories`, `room_types`, `amenities`, `amenity_room_type`, and `rooms`. A room belongs to a floor, category, and type; room types have amenities.

Operations are represented by `clients`, `reservations`, `room_blocks`, `payments`, `housekeeping_tasks`, and `maintenance_tasks`. Clients have reservations and payments; reservations belong to a client, room, source, and creator and have many payments; rooms own reservations, blocks, housekeeping tasks, and maintenance tasks. Reservation audit fields track updates, cancellation/no-show actors and timestamps, and reservations use soft deletion. `reservation_sequences` provides collision-safe, configurable human-readable reservation codes.

## Status and workflow strategy

- Reservation: pending, confirmed, checked in, checked out, cancelled, no-show.
- Room operational status: available, reserved, occupied, must clean, maintenance, blocked.
- Housekeeping condition: clean or dirty, independent of operational status.
- Payment status: pending, paid, failed, refunded, voided; only paid payments count toward the balance.
- Task status and priority are shared vocabulary for housekeeping and maintenance, not a generic record status.

Check-in/check-out/cancel/no-show and completion services use database transactions and row locks. Check-out changes the room to must-clean/dirty and creates a cleaning task. Maintenance completion leaves the room needing inspection/cleaning.

Reservation creation and room reassignment lock the affected room row before checking availability and writing the reservation. Room status synchronization derives current/future reservation occupancy while preserving the independent housekeeping condition; dated blocks and active maintenance take precedence. The Reservations page filters by check-in date, while Room Planning queries all inventory-consuming stays overlapping the visible calendar range.

## Reusable UI foundation for the next phase

The authenticated shell should be one Blade layout with shared sidebar/topbar components. Planned components are `brand`, `sidebar`, `topbar`, `page-header`, `toolbar`, `card`, `data-table`, `status-badge`, `icon-button`, `modal`, `form-field`, `tabs`, `metric-card`, and `empty-state`. The screenshots' compact desktop proportions, restrained radii, thin borders, and light/dark surfaces should be implemented in that shared layer.

## Authorization strategy

Policies and route middleware should authorize capabilities such as `reservations.checkin` and `reports.export`. Templates should use Laravel `@can`, never scattered role-name conditionals. `AppServiceProvider` contains the single super-administrator Gate override; ordinary access is permission-based through the user's role.

## Phase 03 implementation

The Reservations module is implemented under `app/Http/Controllers/Reservations`, with Form Requests, `ReservationService`, `ReservationStateMachine`, `ReservationWorkflowService`, CSV export, lifecycle events, policy checks, and Blade modal workflows. Room Planning is implemented under `RoomPlanningController` and uses one eager-loaded overlap query for the visible range; it does not duplicate availability logic. Payments remain read-only in reservation details until the planned payment module is built.

## Future work (not part of Phase 03)

The remaining modules can build on this source of truth: payment/invoice workflows, live dashboard/report queries, clients/rooms administration, housekeeping/maintenance administration, staff/settings, and integrations. Full payment/invoice implementation is intentionally deferred.

## Risks and open decisions

- Currency conversion requires an explicit, audited conversion workflow before allowing a base-currency change.
- Maintenance tasks without a scheduled interval cannot block a future date range; the room's maintenance status intentionally blocks booking until cleared.
- Payment posting currently rejects overpayment; refunds and credit balances need a later accounting decision.
- A production deployment should add audit events, notification delivery, concurrency tests, and database-specific migration checks for MySQL.
