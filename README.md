# Lodgix Hotel Management System

Lodgix is a Laravel-based hotel operations platform for managing reservations, rooms, guests, staff access, housekeeping, maintenance, payments, notifications, and operational reporting in one workspace.

## What it includes

- Reservation lifecycle management, check-in, check-out, cancellation, and no-show workflows
- Room planning, room categories, room types, availability, and housekeeping status
- Staff profiles, role-based access, permissions, two-factor authentication, and activity controls
- Maintenance and task tracking connected to rooms and reservations
- Payment records, exports, webhook handling, notification preferences, and reports
- Database-backed profile avatars and production deployment support

## Technology

- PHP 8.2+
- Laravel 12
- Laravel Jetstream
- Blade, JavaScript, Tailwind CSS, and Vite
- MySQL for production; SQLite is supported for local development and tests
- PHPUnit for automated tests

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For the local database and mail configuration, update `.env` with values appropriate for your environment. Never commit `.env` or production credentials.

## Useful commands

```bash
php artisan test
npm run build
php artisan route:list
```

## Deployment

The application is deployed publicly at [lodgix.up.railway.app](https://lodgix.up.railway.app). Production configuration, database credentials, and mail credentials are managed through the hosting environment rather than the repository.

## Project status

This is an actively developed business operations system. The public repository documents the application structure and implementation; production data and credentials are not included.
