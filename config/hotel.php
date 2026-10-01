<?php

return [
    // Temporarily keep the setup wizard out of the normal entry flow. Set
    // HOTEL_SETUP_ENABLED=true when the wizard is ready to be used again.
    'setup' => [
        'enabled' => filter_var(env('HOTEL_SETUP_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'brand' => [
        'name' => env('HOTEL_BRAND_NAME', 'Lodgix'),
        // Public branding is kept separate from internal product metadata so
        // the public site can evolve without renaming historical records or
        // changing the authenticated PMS vocabulary.
        'product_name' => env('HOTEL_PUBLIC_PRODUCT_NAME', 'Lodgix'),
        'short_name' => env('HOTEL_PUBLIC_SHORT_NAME', 'Lodgix'),
        'tagline' => env('HOTEL_BRAND_TAGLINE', 'Hotel Management System'),
        'logo_light' => env('HOTEL_PUBLIC_LOGO_LIGHT', 'assets/branding/lodgix.png'),
        'logo_dark' => env('HOTEL_PUBLIC_LOGO_DARK', 'assets/branding/lodgix.png'),
        'mark' => env('HOTEL_PUBLIC_MARK', 'assets/branding/lodgix-mark.png'),
        'favicon' => env('HOTEL_PUBLIC_FAVICON', 'favicon.ico'),
        'apple_touch_icon' => env('HOTEL_PUBLIC_APPLE_TOUCH_ICON', 'apple-touch-icon.png'),
        'support_email' => env('HOTEL_SUPPORT_EMAIL'),
        'support_url' => env('HOTEL_SUPPORT_URL'),
    ],

    'contact' => [
        'email' => env('CONTACT_EMAIL', env('HOTEL_SUPPORT_EMAIL')),
    ],

    // Product metadata is kept separate from the framework/runtime versions so
    // the About and Updates screens can describe the packaged desktop build.
    'product' => [
        'name' => env('HOTEL_PRODUCT_NAME', 'HotelDesk'),
        'version' => env('HOTEL_PRODUCT_VERSION', '0.1.1'),
        'edition' => env('HOTEL_PRODUCT_EDITION', 'Envato'),
        'electron' => env('HOTEL_ELECTRON_VERSION', '43.1.0'),
        'node' => env('HOTEL_NODE_VERSION', '24.18.0'),
        'chromium' => env('HOTEL_CHROMIUM_VERSION', '150.0.7781.47'),
        'platform' => env('HOTEL_PLATFORM', PHP_OS_FAMILY === 'Windows' ? 'win32-x64' : strtolower(PHP_OS).'-'.php_uname('m')),
        'packaged' => filter_var(env('HOTEL_PACKAGED', true), FILTER_VALIDATE_BOOL),
        'data_directory' => env('HOTEL_DATA_DIRECTORY'),
        'update_server' => env('HOTEL_UPDATE_SERVER', 'HotelDesk.app'),
        'update_url' => env('HOTEL_UPDATE_URL', 'https://hoteldesk.app/updates/wsx-hotel-management-system'),
        'update_isolation' => env('HOTEL_UPDATE_ISOLATION', 'product-slug/edition/platform/architecture/release-channel'),
        'architecture' => env('HOTEL_ARCHITECTURE', PHP_INT_SIZE === 8 ? 'x64' : 'x86'),
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

    'pos' => [
        'require_shift' => filter_var(env('POS_REQUIRE_SHIFT', false), FILTER_VALIDATE_BOOL),
        'default_tax_rate' => (float) env('POS_DEFAULT_TAX_RATE', 0),
    ],

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
        'finance.view', 'finance.accounts.view', 'finance.accounts.manage', 'finance.payments.view', 'finance.payments.create', 'finance.expenses.view', 'finance.expenses.create', 'finance.expenses.submit', 'finance.expenses.approve', 'finance.expenses.reject', 'finance.expenses.pay', 'finance.expenses.reverse', 'finance.transfers.create', 'finance.transfers.approve', 'finance.petty_cash.manage', 'finance.bank.manage', 'finance.reconcile', 'finance.refunds', 'finance.adjustments', 'finance.reports.view', 'finance.reports.export',
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
        'contact_enquiries.view', 'contact_enquiries.manage',
        'website.view', 'website.pages.manage', 'website.pages.publish', 'website.media.manage',
        'website.navigation.manage', 'website.pricing.manage', 'website.seo.manage',
        'website.enquiries.view', 'website.enquiries.manage', 'website.settings.manage',
        'announcements.view', 'announcements.create', 'announcements.update', 'announcements.publish',
        'announcements.archive', 'announcements.statistics', 'announcements.manage',
        'announcements.manage_categories', 'announcements.manage_audience', 'announcements.send_email',
        'announcements.send_browser_notification',
        'pos.access', 'pos.sell', 'pos.view_orders', 'pos.view_all_orders', 'pos.charge_room', 'pos.discount', 'pos.void', 'pos.refund',
        'pos.products.view', 'pos.products.manage', 'pos.categories.manage', 'pos.outlets.manage', 'pos.shifts.open', 'pos.shifts.close',
        'pos.shifts.view_all', 'pos.reports.view', 'pos.receipts.view', 'pos.manage',
    ],

    // Only explicitly verified application-owned names may be retired.
    'retired_permissions' => [],

    'api_token_scopes' => [
        'rooms:read', 'availability:read',
        'reservations:read', 'reservations:write',
        'clients:read',
        'payments:read', 'payments:write', 'payments:confirm', 'payments:manage',
        'pos:read', 'pos:write', 'pos:manage',
        'finance:read', 'invoices:read', 'reports:read', 'staff:read',
    ],
];
