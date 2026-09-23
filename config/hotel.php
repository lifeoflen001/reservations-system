<?php

return [
    // Temporarily keep the setup wizard out of the normal entry flow. Set
    // HOTEL_SETUP_ENABLED=true when the wizard is ready to be used again.
    'setup' => [
        'enabled' => filter_var(env('HOTEL_SETUP_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'brand' => [
        'name' => env('HOTEL_BRAND_NAME', 'Lodgix'),
        'tagline' => env('HOTEL_BRAND_TAGLINE', 'Hotel Management System'),
    ],

    'email' => [
        // Keep RFC-reserved addresses available to automated tests, but never
        // permit them to reach a real provider in production.
        'allow_reserved_recipients' => filter_var(
            env('HOTEL_ALLOW_RESERVED_EMAILS', env('APP_ENV') === 'testing'),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'defaults' => [
        'property_name' => env('HOTEL_PROPERTY_NAME', 'HotelDesk Property'),
        'language' => 'en',
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
        'timezone' => env('APP_TIMEZONE', 'Africa/Dar_es_Salaam'),
    ],

    'reservation_code_prefix' => env('HOTEL_RESERVATION_CODE_PREFIX', 'WSX'),

    'database_backup' => [
        'binary' => env('DB_DUMP_BINARY'),
        'path' => env('DB_BACKUP_PATH', storage_path('app/backups')),
    ],

    'permissions' => [
        'dashboard.view',
        'reservations.view', 'reservations.create', 'reservations.update', 'reservations.delete',
        'reservations.checkin', 'reservations.checkout', 'reservations.cancel', 'reservations.mark_no_show', 'reservations.export',
        'room_planning.view',
        'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.complete', 'tasks.reopen', 'tasks.comment', 'tasks.upload', 'tasks.track_time', 'tasks.archive', 'tasks.view_all_departments', 'tasks.manage',
        'rooms.view', 'rooms.manage', 'rooms.create', 'rooms.update', 'rooms.archive', 'rooms.manage_status',
        'room_categories.view', 'room_categories.manage', 'room_types.view', 'room_types.manage', 'floors.manage',
        'clients.view', 'clients.create', 'clients.update', 'clients.archive', 'clients.manage', 'clients.view_sensitive',
        'housekeeping.view', 'housekeeping.manage', 'housekeeping.create', 'housekeeping.update', 'housekeeping.delete', 'housekeeping.complete',
        'maintenance.view', 'maintenance.manage', 'maintenance.create', 'maintenance.update', 'maintenance.delete', 'maintenance.complete',
        'payments.view', 'payments.create', 'payments.update', 'payments.void', 'payments.refund', 'payments.delete', 'payments.print', 'payments.export',
        'invoices.view', 'invoices.print', 'invoices.download',
        'reports.view', 'reports.export',
        'staff.view', 'staff.create', 'staff.update', 'staff.disable', 'staff.reset_password', 'staff.manage',
        'roles.view', 'roles.manage', 'departments.view', 'departments.manage',
        'settings.view', 'settings.manage', 'reservation_sources.view', 'reservation_sources.manage',
        'security.view', 'security.manage', 'database.view', 'license.view', 'updates.view', 'system.about.view',
        'integrations.view', 'integrations.manage', 'email_settings.view', 'email_settings.manage',
        'whatsapp.view', 'whatsapp.manage', 'payment_gateways.view', 'payment_gateways.manage',
        'booking_channels.view', 'booking_channels.manage', 'booking_channels.sync',
        'api_tokens.view', 'api_tokens.manage', 'webhooks.view', 'webhooks.manage', 'webhooks.retry',
        'notifications.view', 'notifications.manage',
    ],
];
