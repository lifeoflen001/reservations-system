@php
    $pages = [
        'product' => [
            'title' => 'Lodgix Product — One Workspace for Hotel Operations',
            'description' => 'See how Lodgix connects the core hotel workflows that teams use every day.',
            'eyebrow' => 'Product overview', 'heading' => 'The operating system for your hotel.',
            'next' => 'public.operations',
            'sections' => [
                ['eyebrow' => 'Front desk', 'heading' => 'Keep reservations and rooms in the same view.', 'description' => 'Move from booking details to guest context, room assignment, arrivals and departures with less handoff.', 'features' => [['icon' => 'calendar', 'title' => 'Reservations', 'description' => 'Manage the stay record from creation through departure.'], ['icon' => 'users', 'title' => 'Guest records', 'description' => 'Keep the client context available to the front desk.'], ['icon' => 'bed', 'title' => 'Room assignment', 'description' => 'Match stays to room inventory and availability.']], 'image' => 'assets/images/landing/lodgix-dashboard-light.webp', 'alt' => 'Sanitized Lodgix dashboard preview.'],
                ['eyebrow' => 'Teams', 'heading' => 'Give each department the context it needs.', 'description' => 'Staff, roles, announcements and tasks help teams coordinate without turning every workflow into a shared inbox.', 'features' => [['icon' => 'users', 'title' => 'Staff', 'description' => 'Organize staff records and departments.'], ['icon' => 'shield', 'title' => 'Roles', 'description' => 'Control access by responsibility.'], ['icon' => 'check-square', 'title' => 'Tasks', 'description' => 'Assign and follow up on operational work.']]],
                ['eyebrow' => 'Connected workflow', 'heading' => 'One record from stay to settlement.', 'description' => 'POS charges, payments, finance movement and reports stay connected to the operating record.', 'features' => [['icon' => 'card', 'title' => 'POS', 'description' => 'Record outlet sales and room charges.'], ['icon' => 'currency', 'title' => 'Finance', 'description' => 'Follow payments, accounts and reconciliation.'], ['icon' => 'chart', 'title' => 'Reports', 'description' => 'Review the activity that matters to management.']]],
            ],
        ],
        'operations' => [
            'title' => 'Lodgix Hotel Operations — Reservations, Rooms and Teams',
            'description' => 'Coordinate reservations, rooms, housekeeping, maintenance and tasks from one operational view.',
            'eyebrow' => 'Hotel operations', 'heading' => 'Keep every stay and every room operation in sync.', 'next' => 'public.pos',
            'heroImage' => 'assets/images/landing/lodgix-room-planning.webp', 'heroAlt' => 'Sanitized Lodgix room planning calendar.',
            'sections' => [
                ['eyebrow' => 'Reservations', 'heading' => 'Move arrivals and departures cleanly through the front desk.', 'description' => 'Keep guest records, reservation details, check-in, check-out and room assignment together.', 'features' => [['icon' => 'calendar', 'title' => 'Reservation records', 'description' => 'Work from a consistent stay record.'], ['icon' => 'users', 'title' => 'Guest context', 'description' => 'Keep client details available when the team needs them.'], ['icon' => 'check', 'title' => 'Stay status', 'description' => 'Make arrivals, in-house stays and departures visible.']]],
                ['eyebrow' => 'Room planning', 'heading' => 'See room readiness across the day.', 'description' => 'Use the planning view to understand assignments, room status and the work still needed before arrival.', 'features' => [['icon' => 'bed', 'title' => 'Room inventory', 'description' => 'Manage room types, categories, floors and statuses.'], ['icon' => 'broom', 'title' => 'Housekeeping', 'description' => 'Assign cleaning work and update readiness.'], ['icon' => 'wrench', 'title' => 'Maintenance', 'description' => 'Track issues, priority and completion.']], 'image' => 'assets/images/landing/lodgix-room-planning.webp', 'alt' => 'Sanitized Lodgix room planning screen.'],
                ['eyebrow' => 'Operational workflow', 'heading' => 'Make the handoff between teams understandable.', 'description' => 'Reservation, arrival, room assignment, stay, room work and departure form one practical daily flow.', 'features' => [['icon' => 'check-square', 'title' => 'Tasks', 'description' => 'Keep work assigned and visible.'], ['icon' => 'bell', 'title' => 'Announcements', 'description' => 'Share targeted updates with teams.'], ['icon' => 'users', 'title' => 'Departments', 'description' => 'Keep responsibility clear across the property.']]],
            ],
        ],
        'pos' => [
            'title' => 'Lodgix POS — From Outlet Sale to Guest Folio',
            'description' => 'Run outlet sales, shifts, receipts and room charges with a clear connection to the guest stay.',
            'eyebrow' => 'POS & guest charges', 'heading' => 'From outlet sale to guest folio.', 'next' => 'public.finance',
            'theme' => 'dark',
            'sections' => [
                ['eyebrow' => 'Selling', 'heading' => 'Keep outlets, products and orders organized.', 'description' => 'Give teams a focused terminal for products, categories and accountable outlet activity.', 'features' => [['icon' => 'card', 'title' => 'Terminal', 'description' => 'Build an order and take immediate payment.'], ['icon' => 'grid', 'title' => 'Products and categories', 'description' => 'Keep the sellable catalogue easy to use.'], ['icon' => 'building', 'title' => 'Outlets', 'description' => 'Separate activity by selling location.']]],
                ['eyebrow' => 'PMS connection', 'heading' => 'Post a room charge without losing the source order.', 'description' => 'A room charge moves from POS order to guest folio and then into payment and finance workflows.', 'features' => [['icon' => 'bed', 'title' => 'Guest charges', 'description' => 'Attach an in-house purchase to the right stay.'], ['icon' => 'document', 'title' => 'Receipts', 'description' => 'Keep the order reference and payment detail identifiable.'], ['icon' => 'currency', 'title' => 'Settlement', 'description' => 'Let the later payment settle the existing charge.']]],
                ['eyebrow' => 'Control', 'heading' => 'Keep every shift and correction accountable.', 'description' => 'Use shift controls, refunds, voids and reporting to preserve a clear sales history.', 'features' => [['icon' => 'calendar', 'title' => 'Shifts', 'description' => 'Open and close accountable cashier activity.'], ['icon' => 'refresh', 'title' => 'Refunds and voids', 'description' => 'Correct a sale without silently deleting history.'], ['icon' => 'chart', 'title' => 'POS reporting', 'description' => 'Review orders, payments and outlet movement.']]],
            ],
        ],
        'finance' => [
            'title' => 'Lodgix Finance — Payments, Accounts and Reconciliation',
            'description' => 'Keep guest payments, accounts, expenses, transfers, petty cash and reconciliation easy to follow.',
            'eyebrow' => 'Finance & payments', 'heading' => 'Know where the money is — and where it moved.', 'next' => 'public.security',
            'theme' => 'dark',
            'sections' => [
                ['eyebrow' => 'Daily finance', 'heading' => 'See balances and movement without losing the source.', 'description' => 'Review guest payments, account balances and recent ledger activity from one finance workspace.', 'features' => [['icon' => 'currency', 'title' => 'Guest payments', 'description' => 'Record settlement against the guest stay.'], ['icon' => 'card', 'title' => 'Account balances', 'description' => 'Follow cash, bank, card and mobile-money movement.'], ['icon' => 'document', 'title' => 'Ledger activity', 'description' => 'Keep the source transaction visible.']]],
                ['eyebrow' => 'Controls', 'heading' => 'Make operating costs and transfers visible.', 'description' => 'Track expenses, approvals, transfers and petty cash with controlled actions and clear status.', 'features' => [['icon' => 'document', 'title' => 'Expenses', 'description' => 'Submit, approve, pay or reverse operating costs.'], ['icon' => 'share', 'title' => 'Transfers', 'description' => 'Record movement between finance accounts.'], ['icon' => 'currency', 'title' => 'Petty cash', 'description' => 'Keep small cash activity accountable.']]],
                ['eyebrow' => 'Reconciliation', 'heading' => 'Compare movement and close the period with confidence.', 'description' => 'Use reconciliation and reporting to review what the system recorded against the statement period.', 'features' => [['icon' => 'check', 'title' => 'Reconciliation', 'description' => 'Compare ledger movement with a statement.'], ['icon' => 'chart', 'title' => 'Reports', 'description' => 'Review financial activity by period.'], ['icon' => 'refresh', 'title' => 'Refunds', 'description' => 'Keep corrections tied to the original financial record.']]],
            ],
        ],
        'security' => [
            'title' => 'Lodgix Security — Roles, Permissions and Control',
            'description' => 'Keep access, sensitive records and financial corrections aligned with each team’s responsibility.',
            'eyebrow' => 'Security & control', 'heading' => 'Give every role the right level of access.', 'next' => 'public.integrations',
            'sections' => [
                ['eyebrow' => 'Access', 'heading' => 'Role-based access for the hotel team.', 'description' => 'Use staff roles and permissions to control which application areas and actions each person can use.', 'features' => [['icon' => 'users', 'title' => 'Staff roles', 'description' => 'Match access to department responsibility.'], ['icon' => 'key', 'title' => 'Permissions', 'description' => 'Control sensitive actions explicitly.'], ['icon' => 'shield', 'title' => 'Authenticated application', 'description' => 'Keep PMS workflows behind sign-in.']]],
                ['eyebrow' => 'Financial protection', 'heading' => 'Protect sensitive activity while keeping it auditable.', 'description' => 'Sensitive finance fields can be restricted or masked, while important actions retain traceable history.', 'features' => [['icon' => 'currency', 'title' => 'Protected financial data', 'description' => 'Limit visibility where appropriate.'], ['icon' => 'history', 'title' => 'Auditable actions', 'description' => 'Keep a history of important activity.'], ['icon' => 'refresh', 'title' => 'Controlled reversals', 'description' => 'Preserve original records during corrections.']]],
                ['eyebrow' => 'Data separation', 'heading' => 'Keep the public website separate from hotel records.', 'description' => 'Public marketing pages are static and do not load reservations, clients, payments, staff or finance balances.', 'features' => [['icon' => 'globe', 'title' => 'Public by design', 'description' => 'No operational records are queried for marketing pages.'], ['icon' => 'shield', 'title' => 'Protected routes', 'description' => 'Authenticated PMS pages remain behind application access.'], ['icon' => 'check', 'title' => 'Clear boundaries', 'description' => 'Integrations and provider readiness are presented honestly.']]],
            ],
        ],
        'integrations' => [
            'title' => 'Lodgix Integrations — Configurable Hotel Workflows',
            'description' => 'Review the verified status of email, notifications, APIs, webhooks and provider-ready workflows.',
            'eyebrow' => 'Integrations', 'heading' => 'Connect supporting services with clear boundaries.', 'next' => 'public.product',
            'sections' => [
                ['eyebrow' => 'Communication', 'heading' => 'Keep the hotel team informed.', 'description' => 'Use configured delivery and in-app workflows for operational updates.', 'features' => [['icon' => 'document', 'title' => 'Email / SMTP', 'description' => 'Configurable through administrator settings.'], ['icon' => 'bell', 'title' => 'Notifications', 'description' => 'Implemented for in-app operational updates.'], ['icon' => 'share', 'title' => 'Announcements', 'description' => 'Share targeted information with teams.']]],
                ['eyebrow' => 'System connections', 'heading' => 'Use controlled API and webhook boundaries.', 'description' => 'Scoped tokens and signed webhook workflows provide a defined foundation for system connections.', 'features' => [['icon' => 'key', 'title' => 'API tokens', 'description' => 'Configurable access for approved integrations.'], ['icon' => 'shield', 'title' => 'Signed webhooks', 'description' => 'Configurable inbound workflow protection.'], ['icon' => 'refresh', 'title' => 'Provider readiness', 'description' => 'Payment workflows are structured for configured providers.']]],
                ['eyebrow' => 'Status', 'heading' => 'Know what is available before you connect.', 'description' => 'Lodgix presents integration status plainly; no live provider or partner relationship is implied here.', 'features' => [['icon' => 'check', 'title' => 'Implemented', 'description' => 'Available in the current application.'], ['icon' => 'settings', 'title' => 'Configurable', 'description' => 'Available when an administrator supplies settings.'], ['icon' => 'card', 'title' => 'Integration ready', 'description' => 'Structured for an approved provider and credentials.']]],
            ],
        ],
    ];
    $page = $pages[$pageKey];
    $pageTitle = $page['title'];
    $pageDescription = $page['description'];
    $structuredData = json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => config('hotel.brand.product_name', 'Lodgix'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => $pageDescription, 'url' => url('/'.$pageKey)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@push('structured-data')<script type="application/ld+json">{!! $structuredData !!}</script>@endpush

@section('content')
    <div class="public-page public-page--{{ $pageKey }}">
        <x-public.page-hero eyebrow="{{ $page['eyebrow'] }}" heading="{{ $page['heading'] }}" description="{{ $pageDescription }}" theme="{{ $page['theme'] ?? 'light' }}">
            @if(!empty($page['heroImage']))<x-public.screenshot-frame browser-shell :src="$page['heroImage']" :width="1846" :height="921" aspect-ratio="1846 / 921" :alt="$page['heroAlt']" loading="eager" fetch-priority="high" />@endif
        </x-public.page-hero>

        @if($pageKey === 'operations')
            <x-public.section theme="dark" class="public-page-workflow"><x-public.section-heading theme="dark" align="center" eyebrow="Daily room workflow" heading="Reservation → arrival → room → stay → departure." description="A practical sequence for the teams who keep the property ready." /><x-public.workflow /></x-public.section>
        @elseif($pageKey === 'pos')
            <x-public.section theme="dark" class="public-page-flow"><x-public.section-heading theme="dark" align="center" eyebrow="PMS connection" heading="POS order → room charge → guest folio → payment → finance." description="A room charge represents the sale once; the later payment settles it." /></x-public.section>
        @endif

        @foreach($page['sections'] as $index => $section)
            @if(!empty($section['image']))
                <x-public.feature-section :eyebrow="$section['eyebrow']" :heading="$section['heading']" :description="$section['description']" :features="$section['features']" :reverse="$index % 2 === 1" :theme="$page['theme'] ?? 'light'"><x-public.screenshot-frame browser-shell :src="$section['image']" :width="1846" :height="921" aspect-ratio="1846 / 921" :alt="$section['alt']" /></x-public.feature-section>
            @else
                <x-public.section class="public-page-section {{ ($page['theme'] ?? 'light') === 'dark' ? 'public-section--dark-alt' : ($index % 2 === 1 ? 'public-section--muted' : '') }}"><x-public.section-heading :eyebrow="$section['eyebrow']" :heading="$section['heading']" :description="$section['description']" /><div class="public-page-card-grid">@foreach($section['features'] as $feature)<x-public.card variant="{{ ($page['theme'] ?? 'light') === 'dark' ? 'dark' : 'feature' }}"><span class="public-card__icon"><x-ui.icon :name="$feature['icon']" size="18" /></span><h3>{{ $feature['title'] }}</h3><p>{{ $feature['description'] }}</p></x-public.card>@endforeach</div></x-public.section>
            @endif
        @endforeach

        @if($pageKey === 'integrations')
            <x-public.section class="public-section--muted public-integration-status"><x-public.section-heading align="center" eyebrow="Integration status" heading="Clear status, no implied partnerships." description="These labels describe the current application foundation and the setup still required by an administrator." /><div class="public-integration-status__grid"><x-public.card variant="feature"><span class="public-status-badge public-status-badge--implemented">IMPLEMENTED</span><h3>Notifications</h3><p>Available in the current application.</p></x-public.card><x-public.card variant="feature"><span class="public-status-badge public-status-badge--configured">CONFIGURABLE</span><h3>Email and APIs</h3><p>Available when approved settings and credentials are supplied.</p></x-public.card><x-public.card variant="feature"><span class="public-status-badge public-status-badge--ready">INTEGRATION READY</span><h3>WhatsApp and payment providers</h3><p>Ready for an approved provider; no live provider is claimed here.</p></x-public.card></div></x-public.section>
        @endif

        <x-public.section theme="dark" class="public-final-cta public-page-cta"><div class="public-final-cta__content"><x-public.eyebrow>Explore the workspace</x-public.eyebrow><h2>See how the next part of the hotel day connects.</h2><p>Move through Lodgix one product area at a time.</p><div class="public-final-cta__actions"><x-public.button :href="route($page['next'])" variant="primary">Continue to {{ $pages[str_replace('public.', '', $page['next'])]['eyebrow'] ?? 'Lodgix' }} <x-ui.icon name="arrow-right" size="16" /></x-public.button><x-public.button :href="auth()->check() ? route('dashboard') : route('login')" variant="dark">{{ auth()->check() ? 'Open Dashboard' : 'Sign In' }}</x-public.button></div></div></x-public.section>
    </div>
@endsection
