<?php

namespace App\Support;

final class WebsiteContentDefaults
{
    public static function pages(): array
    {
        return [
            'home' => [
                'name' => 'Home', 'route_name' => 'public.home',
                'seo_title' => 'Lodgix — Hotel Management System for Connected Hotel Operations',
                'seo_description' => 'Manage reservations, rooms, hotel operations, POS, payments, finance and reporting from one connected Lodgix workspace for daily hotel operations.',
                'sections' => [
                    ['key' => 'hero', 'type' => 'hero', 'content' => ['eyebrow' => 'Complete hotel operations platform', 'heading' => 'Run the daily hotel operation from one clear workspace.', 'description' => 'Manage reservations, rooms, hotel operations, POS, payments, finance and reporting from one connected Lodgix workspace for daily hotel operations.', 'primary_cta_label' => 'Explore the platform', 'primary_cta_url' => '/product']],
                    ['key' => 'capabilities', 'type' => 'capability_grid', 'content' => ['heading' => 'The working tools behind the front desk.', 'description' => 'Choose a product area to see how the pieces fit together.', 'items' => [
                        ['icon' => 'grid', 'title' => 'Product', 'description' => 'A connected operating view for the hotel day.', 'route' => 'public.product'],
                        ['icon' => 'calendar', 'title' => 'Hotel operations', 'description' => 'Reservations, rooms, housekeeping and maintenance in sync.', 'route' => 'public.operations'],
                        ['icon' => 'card', 'title' => 'POS & guest charges', 'description' => 'Move outlet sales cleanly into payment or folio.', 'route' => 'public.pos'],
                        ['icon' => 'currency', 'title' => 'Finance & payments', 'description' => 'Follow balances, expenses, transfers and reconciliation.', 'route' => 'public.finance'],
                        ['icon' => 'shield', 'title' => 'Security & control', 'description' => 'Keep access and sensitive hotel activity accountable.', 'route' => 'public.security'],
                        ['icon' => 'share', 'title' => 'Integrations', 'description' => 'Connect supporting services with clear boundaries.', 'route' => 'public.integrations'],
                    ]]],
                    ['key' => 'final_cta', 'type' => 'cta', 'content' => ['eyebrow' => 'Bring it together', 'heading' => 'Give every hotel team a clearer operating view.', 'description' => 'Reservations, rooms, operations, POS, finance and reporting — in one Lodgix workspace.']],
                ],
            ],
            'product' => [
                'name' => 'Product', 'route_name' => 'public.product', 'seo_title' => 'Lodgix Product — One Workspace for Hotel Operations', 'seo_description' => 'See how Lodgix connects the core hotel workflows that teams use every day.',
                'hero' => ['eyebrow' => 'Product overview', 'heading' => 'The operating system for your hotel.'],
                'sections' => [
                    ['key' => 'front_desk', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Front desk', 'heading' => 'Keep reservations and rooms in the same view.', 'description' => 'Move from booking details to guest context, room assignment, arrivals and departures with less handoff.']],
                    ['key' => 'teams', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Teams', 'heading' => 'Give each department the context it needs.', 'description' => 'Staff, roles, announcements and tasks help teams coordinate without turning every workflow into a shared inbox.']],
                    ['key' => 'connected_workflow', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Connected workflow', 'heading' => 'One record from stay to settlement.', 'description' => 'POS charges, payments, finance movement and reports stay connected to the operating record.']],
                ],
            ],
            'operations' => ['name' => 'Operations', 'route_name' => 'public.operations', 'seo_title' => 'Lodgix Hotel Operations — Reservations, Rooms and Teams', 'seo_description' => 'Coordinate reservations, rooms, housekeeping, maintenance and tasks from one operational view.', 'hero' => ['eyebrow' => 'Hotel operations', 'heading' => 'Keep every stay and every room operation in sync.'], 'sections' => [
                ['key' => 'reservations', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Reservations', 'heading' => 'Move arrivals and departures cleanly through the front desk.', 'description' => 'Keep guest records, reservation details, check-in, check-out and room assignment together.']],
                ['key' => 'room_planning', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Room planning', 'heading' => 'See room readiness across the day.', 'description' => 'Use the planning view to understand assignments, room status and the work still needed before arrival.']],
                ['key' => 'workflow', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Operational workflow', 'heading' => 'Make the handoff between teams understandable.', 'description' => 'Reservation, arrival, room assignment, stay, room work and departure form one practical daily flow.']],
            ]],
            'pos' => ['name' => 'POS', 'route_name' => 'public.pos', 'seo_title' => 'Lodgix POS — From Outlet Sale to Guest Folio', 'seo_description' => 'Run outlet sales, shifts, receipts and room charges with a clear connection to the guest stay.', 'hero' => ['eyebrow' => 'POS & guest charges', 'heading' => 'From outlet sale to guest folio.'], 'sections' => [
                ['key' => 'selling', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Selling', 'heading' => 'Keep outlets, products and orders organized.', 'description' => 'Give teams a focused terminal for products, categories and accountable outlet activity.']],
                ['key' => 'pms_connection', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'PMS connection', 'heading' => 'Post a room charge without losing the source order.', 'description' => 'A room charge moves from POS order to guest folio and then into payment and finance workflows.']],
                ['key' => 'control', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Control', 'heading' => 'Keep every shift and correction accountable.', 'description' => 'Use shift controls, refunds, voids and reporting to preserve a clear sales history.']],
            ]],
            'finance' => ['name' => 'Finance', 'route_name' => 'public.finance', 'seo_title' => 'Lodgix Finance — Payments, Accounts and Reconciliation', 'seo_description' => 'Keep guest payments, accounts, expenses, transfers, petty cash and reconciliation easy to follow.', 'hero' => ['eyebrow' => 'Finance & payments', 'heading' => 'Know where the money is — and where it moved.'], 'sections' => [
                ['key' => 'daily_finance', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Daily finance', 'heading' => 'See balances and movement without losing the source.', 'description' => 'Review guest payments, account balances and recent ledger activity from one finance workspace.']],
                ['key' => 'controls', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Controls', 'heading' => 'Make operating costs and transfers visible.', 'description' => 'Track expenses, approvals, transfers and petty cash with controlled actions and clear status.']],
                ['key' => 'reconciliation', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Reconciliation', 'heading' => 'Compare movement and close the period with confidence.', 'description' => 'Use reconciliation and reporting to review what the system recorded against the statement period.']],
            ]],
            'security' => ['name' => 'Security', 'route_name' => 'public.security', 'seo_title' => 'Lodgix Security — Roles, Permissions and Control', 'seo_description' => 'Keep access, sensitive records and financial corrections aligned with each team’s responsibility.', 'hero' => ['eyebrow' => 'Security & control', 'heading' => 'Give every role the right level of access.'], 'sections' => [
                ['key' => 'access', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Access', 'heading' => 'Role-based access for the hotel team.', 'description' => 'Use staff roles and permissions to control which application areas and actions each person can use.']],
                ['key' => 'financial_protection', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Financial protection', 'heading' => 'Protect sensitive activity while keeping it auditable.', 'description' => 'Sensitive finance fields can be restricted or masked, while important actions retain traceable history.']],
                ['key' => 'accountability', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Accountability', 'heading' => 'Keep important changes traceable.', 'description' => 'Audit history and controlled corrections help teams review sensitive activity without losing the original record.']],
            ]],
            'integrations' => ['name' => 'Integrations', 'route_name' => 'public.integrations', 'seo_title' => 'Lodgix Integrations — Configurable Hotel Workflows', 'seo_description' => 'See how email, notifications, APIs, webhooks and provider-dependent connections fit into hotel workflows.', 'hero' => ['eyebrow' => 'Integrations', 'heading' => 'Connect the services behind your hotel.'], 'sections' => [
                ['key' => 'communication', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Communication', 'heading' => 'Keep the hotel team informed.', 'description' => 'Support team updates with in-app notifications and email delivery options.']],
                ['key' => 'system_connections', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'System connections', 'heading' => 'Connect systems with clear controls.', 'description' => 'API access and webhook workflows provide controlled ways to connect supporting services.']],
                ['key' => 'provider_options', 'type' => 'feature_grid', 'content' => ['eyebrow' => 'Provider options', 'heading' => 'Understand what each connection needs.', 'description' => 'Some workflows are part of Lodgix today; others need service credentials or a compatible provider.']],
            ]],
            'pricing' => ['name' => 'Pricing', 'route_name' => 'public.pricing', 'seo_title' => 'Lodgix Pricing — Hotel Management System Plans', 'seo_description' => 'Explore Lodgix plan options for hotel operations, POS, finance, reporting and integrations.', 'hero' => ['eyebrow' => 'Pricing for independent hotels and lodges', 'heading' => 'Flexible plans built around your property.']],
            'contact' => ['name' => 'Contact', 'route_name' => 'public.contact', 'seo_title' => 'Contact Lodgix — Hotel Management System Enquiries', 'seo_description' => 'Contact Lodgix about pricing, implementation, integrations or hotel-management requirements.', 'hero' => ['eyebrow' => 'Lodgix enquiries', 'heading' => 'Let’s talk about your hotel.', 'description' => 'Tell us what you operate and what you want Lodgix to handle.']],
        ];
    }

    public static function navigation(): array
    {
        return [
            ['location' => 'header', 'label' => 'Product', 'destination_type' => 'route', 'destination' => 'public.product', 'position' => 10],
            ['location' => 'header', 'label' => 'Solutions', 'destination_type' => 'group', 'destination' => 'solutions', 'position' => 20],
            ['location' => 'header', 'label' => 'Pricing', 'destination_type' => 'route', 'destination' => 'public.pricing', 'position' => 30],
            ['location' => 'header', 'label' => 'Contact', 'destination_type' => 'route', 'destination' => 'public.contact', 'position' => 40],
        ];
    }

    public static function pricingPlans(): array
    {
        return [
            ['name' => 'Starter', 'short_description' => 'Essential hotel operations for smaller properties.', 'features' => ['Reservations and guest records', 'Rooms and room planning', 'Housekeeping, maintenance and tasks', 'Operational reports']],
            ['name' => 'Professional', 'short_description' => 'Connected operations, POS and finance.', 'features' => ['Core hotel operations', 'POS and guest charges', 'Payments and finance workflows', 'Staff access and reporting needs']],
            ['name' => 'Enterprise', 'short_description' => 'Advanced controls and connections for complex requirements.', 'features' => ['Advanced roles and permissions', 'Integration requirements', 'Implementation and support needs']],
        ];
    }
}
