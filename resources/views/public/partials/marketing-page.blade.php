@php
$pages = [
'product' => [
'title' => 'Lodgix Product — One Workspace for Hotel Operations',
'description' => 'See how Lodgix connects the core hotel workflows that teams use every day.',
'eyebrow' => 'Product overview', 'heading' => 'The operating system for your hotel.',
'heroImage' => 'assets/images/landing/lodgix-dashboard-light.jpg', 'heroAlt' => 'Lodgix hotel operations dashboard with reservation, room and finance summaries.',
'next' => 'public.operations',
'sections' => [
['eyebrow' => 'Front desk', 'heading' => 'Keep reservations and rooms in the same view.', 'description' => 'Move from booking details to guest context, room assignment, arrivals and departures with less handoff.', 'features' => [['icon' => 'calendar', 'title' => 'Reservations', 'description' => 'Manage the stay record from creation through departure.'], ['icon' => 'users', 'title' => 'Guest records', 'description' => 'Keep the client context available to the front desk.'], ['icon' => 'bed', 'title' => 'Room assignment', 'description' => 'Match stays to room inventory and availability.']]],
['eyebrow' => 'Teams', 'heading' => 'Give each department the context it needs.', 'description' => 'Staff, roles, announcements and tasks help teams coordinate without turning every workflow into a shared inbox.', 'features' => [['icon' => 'users', 'title' => 'Staff', 'description' => 'Organize staff records and departments.'], ['icon' => 'shield', 'title' => 'Roles', 'description' => 'Control access by responsibility.'], ['icon' => 'check-square', 'title' => 'Tasks', 'description' => 'Assign and follow up on operational work.']]],
['eyebrow' => 'Connected workflow', 'heading' => 'One record from stay to settlement.', 'description' => 'POS charges, payments, finance movement and reports stay connected to the operating record.', 'features' => [['icon' => 'card', 'title' => 'POS', 'description' => 'Record outlet sales and room charges.'], ['icon' => 'currency', 'title' => 'Finance', 'description' => 'Follow payments, accounts and reconciliation.'], ['icon' => 'chart', 'title' => 'Reports', 'description' => 'Review the activity that matters to management.']]],
],
],
'operations' => [
'title' => 'Lodgix Hotel Operations — Reservations, Rooms and Teams',
'description' => 'Coordinate reservations, rooms, housekeeping, maintenance and tasks from one operational view.',
'eyebrow' => 'Hotel operations', 'heading' => 'Keep every stay and every room operation in sync.', 'next' => 'public.pos',
'heroImage' => 'assets/images/landing/lodgix-room-planning.jpg', 'heroAlt' => 'Lodgix room planning calendar showing room assignments and dates.',
'sections' => [
['eyebrow' => 'Reservations', 'heading' => 'Move arrivals and departures cleanly through the front desk.', 'description' => 'Keep guest records, reservation details, check-in, check-out and room assignment together.', 'features' => [['icon' => 'calendar', 'title' => 'Reservation records', 'description' => 'Work from a consistent stay record.'], ['icon' => 'users', 'title' => 'Guest context', 'description' => 'Keep client details available when the team needs them.'], ['icon' => 'check', 'title' => 'Stay status', 'description' => 'Make arrivals, in-house stays and departures visible.']]],
['eyebrow' => 'Room planning', 'heading' => 'See room readiness across the day.', 'description' => 'Use the planning view to understand assignments, room status and the work still needed before arrival.', 'features' => [['icon' => 'bed', 'title' => 'Room inventory', 'description' => 'Manage room types, categories, floors and statuses.'], ['icon' => 'broom', 'title' => 'Housekeeping', 'description' => 'Assign cleaning work and update readiness.'], ['icon' => 'wrench', 'title' => 'Maintenance', 'description' => 'Track issues, priority and completion.']], 'image' => 'assets/images/landing/lodgix-room-planning.jpg', 'alt' => 'Lodgix room planning screen with room assignments and housekeeping status.'],
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
'eyebrow' => 'Finance & payments', 'heading' => 'Know where the money is — and where it moved.', 'next' => 'public.pricing',
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
['eyebrow' => 'Access', 'heading' => 'Role-based access for the hotel team.', 'description' => 'Use staff roles and permissions to control which application areas and actions each person can use.', 'features' => [['icon' => 'users', 'title' => 'Staff roles', 'description' => 'Match access to department responsibility.'], ['icon' => 'key', 'title' => 'Permissions', 'description' => 'Control sensitive actions explicitly.'], ['icon' => 'shield', 'title' => 'Sign-in protection', 'description' => 'Keep hotel workflows behind authenticated access.']]],
['eyebrow' => 'Financial protection', 'heading' => 'Protect sensitive activity while keeping it auditable.', 'description' => 'Sensitive finance fields can be restricted or masked, while important actions retain traceable history.', 'features' => [['icon' => 'currency', 'title' => 'Protected financial data', 'description' => 'Limit visibility where appropriate.'], ['icon' => 'history', 'title' => 'Auditable actions', 'description' => 'Keep a history of important activity.'], ['icon' => 'refresh', 'title' => 'Controlled reversals', 'description' => 'Preserve original records during corrections.']]],
['eyebrow' => 'Accountability', 'heading' => 'Keep important changes traceable.', 'description' => 'Audit history and controlled corrections help teams review sensitive activity without losing the original record.', 'features' => [['icon' => 'history', 'title' => 'Action history', 'description' => 'Review important activity.'], ['icon' => 'refresh', 'title' => 'Controlled corrections', 'description' => 'Preserve records when correcting an action.'], ['icon' => 'key', 'title' => 'Sensitive permissions', 'description' => 'Restrict actions according to staff responsibilities.']]],
],
],
'integrations' => [
'title' => 'Lodgix Integrations — Configurable Hotel Workflows',
'description' => 'See how email, notifications, APIs, webhooks and provider-dependent connections fit into hotel workflows.',
'eyebrow' => 'Integrations', 'heading' => 'Connect the services behind your hotel.', 'next' => 'public.product',
'sections' => [
['eyebrow' => 'Communication', 'heading' => 'Keep the hotel team informed.', 'description' => 'Support team updates with in-app notifications and email delivery options.', 'features' => [['icon' => 'document', 'title' => 'Email / SMTP', 'description' => 'Email delivery can be configured for your environment.'], ['icon' => 'bell', 'title' => 'Notifications', 'description' => 'In-app operational updates are available.'], ['icon' => 'share', 'title' => 'Announcements', 'description' => 'Share targeted information with teams.']]],
['eyebrow' => 'System connections', 'heading' => 'Connect systems with clear controls.', 'description' => 'API access and webhook workflows provide controlled ways to connect supporting services.', 'features' => [['icon' => 'key', 'title' => 'API tokens', 'description' => 'Set up access for approved integrations.'], ['icon' => 'shield', 'title' => 'Webhooks', 'description' => 'Support protected, event-based workflows.'], ['icon' => 'refresh', 'title' => 'Provider connections', 'description' => 'Payment and messaging connections depend on provider setup.']]],
['eyebrow' => 'Provider options', 'heading' => 'Understand what each connection needs.', 'description' => 'Some workflows are part of Lodgix today; others need service credentials or a compatible provider.', 'features' => [['icon' => 'check', 'title' => 'In-app notifications', 'description' => 'Available in Lodgix.'], ['icon' => 'settings', 'title' => 'Email and APIs', 'description' => 'Require environment and access setup.'], ['icon' => 'card', 'title' => 'WhatsApp and payment providers', 'description' => 'Provider-dependent; availability depends on the chosen service.']]],
],
],
];
$page = $pages[$pageKey];
$pageTitle = $page['title'];
$pageDescription = $page['description'];
$structuredData = json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => config('hotel.brand.product_name', 'Lodgix'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => $pageDescription, 'url' => url('/'.$pageKey)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@push('structured-data')<script type="application/ld+json">
    {!! $structuredData !!}
</script>@endpush

@section('content')
<div class="public-page public-page--{{ $pageKey }}">
    <x-public.page-hero eyebrow="{{ $page['eyebrow'] }}" heading="{{ $page['heading'] }}" description="{{ $pageDescription }}" theme="{{ $page['theme'] ?? 'light' }}" layout="{{ !empty($page['heroImage']) ? 'split' : 'centered' }}">
        @if(!empty($page['heroImage']))<x-public.screenshot-frame class="public-page-hero__screenshot" browser-shell :src="$page['heroImage']" :mobile-src="$pageKey === 'product' ? 'assets/images/landing/lodgix-dashboard-mobile.webp' : null" :srcset="$pageKey === 'product' ? asset('assets/images/landing/lodgix-dashboard-light-960.jpg').' 960w, '.asset('assets/images/landing/lodgix-dashboard-light-1440.jpg').' 1440w, '.asset('assets/images/landing/lodgix-dashboard-light.jpg').' 1654w' : asset('assets/images/landing/lodgix-room-planning-960.jpg').' 960w, '.asset('assets/images/landing/lodgix-room-planning-1440.jpg').' 1440w, '.asset('assets/images/landing/lodgix-room-planning.jpg').' 1846w'" sizes="(max-width: 900px) calc(100vw - 36px), 58vw" :width="$pageKey === 'product' ? 1654 : 1846" :height="921" :aspect-ratio="$pageKey === 'product' ? '1654 / 921' : '1846 / 921'" :alt="$page['heroAlt']" loading="eager" fetch-priority="high" />@endif
    </x-public.page-hero>

    @if($pageKey === 'finance')
    <x-public.section class="public-finance-flow public-section--muted">
        <x-public.container>
            <ol class="public-finance-flow__grid" aria-label="Finance workflow">
                @foreach([['card', 'Guest payment'], ['currency', 'Account movement'], ['check', 'Reconciliation']] as [$icon, $label])
                    <li><span class="public-finance-flow__icon"><x-ui.icon :name="$icon" size="18" /></span><strong>{{ $label }}</strong></li>
                @endforeach
            </ol>
        </x-public.container>
    </x-public.section>
    @endif

    @if($pageKey === 'integrations')
    <x-public.section class="public-integration-overview">
        <div class="public-integration-overview__grid" aria-label="Integration categories and setup requirements">
            @foreach([
                ['document', 'Email / SMTP', 'Configurable', 'Requires environment setup'],
                ['bell', 'Notifications', 'Implemented', 'Available in Lodgix'],
                ['key', 'APIs & webhooks', 'Configurable', 'Requires access setup'],
                ['share', 'WhatsApp', 'Provider-dependent', 'Requires a compatible service'],
                ['card', 'Payment providers', 'Provider-dependent', 'Availability varies by provider'],
            ] as [$icon, $label, $status, $description])
                <article class="public-integration-overview__item">
                    <span class="public-integration-overview__icon"><x-ui.icon :name="$icon" size="17" /></span>
                    <span class="public-integration-overview__copy"><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                    <span class="public-status-badge {{ $status === 'Implemented' ? 'public-status-badge--implemented' : ($status === 'Configurable' ? 'public-status-badge--configured' : 'public-status-badge--ready') }}">{{ $status }}</span>
                </article>
            @endforeach
        </div>
    </x-public.section>
    @endif

    @if($pageKey === 'operations')
    <x-public.section class="public-page-workflow public-section--muted"><x-public.section-heading align="center" eyebrow="Daily room workflow" heading="Reservation → arrival → room → stay → departure." description="A practical sequence for the teams who keep the property ready." /><x-public.workflow /></x-public.section>
    @elseif($pageKey === 'pos')
    <x-public.section class="public-page-flow"><x-public.section-heading align="center" eyebrow="PMS connection" heading="One sale, connected through settlement." description="Follow the room-charge path from outlet order to guest folio and payment." />
        <ol class="public-pos-flow" aria-label="POS room-charge workflow">
            @foreach([['card', 'POS order'], ['bed', 'Room charge'], ['users', 'Guest folio'], ['currency', 'Payment'], ['chart', 'Finance']] as [$icon, $label])
                <li><span class="public-pos-flow__icon"><x-ui.icon :name="$icon" size="18" /></span><strong>{{ $label }}</strong></li>
            @endforeach
        </ol>
    </x-public.section>
    @endif

    @foreach($page['sections'] as $index => $section)
    @if(!empty($section['image']))
    <x-public.feature-section :eyebrow="$section['eyebrow']" :heading="$section['heading']" :description="$section['description']" :features="$section['features']" :reverse="$index % 2 === 1" :theme="'light'"><x-public.screenshot-frame browser-shell :src="$section['image']" :srcset="asset('assets/images/landing/lodgix-room-planning-960.jpg').' 960w, '.asset('assets/images/landing/lodgix-room-planning-1440.jpg').' 1440w, '.asset('assets/images/landing/lodgix-room-planning.jpg').' 1846w'" sizes="(max-width: 900px) calc(100vw - 36px), 62vw" :width="1846" :height="921" aspect-ratio="1846 / 921" :alt="$section['alt']" /></x-public.feature-section>
    @else
    @php
        $sectionIsDark = ($pageKey === 'pos' && $index === 2) || ($pageKey === 'finance' && $index === 1);
        $sectionClass = $sectionIsDark ? 'public-section--dark' : ($index % 2 === 1 ? 'public-section--muted' : '');
    @endphp
    <x-public.section class="public-page-section {{ $sectionClass }}"><x-public.section-heading :eyebrow="$section['eyebrow']" :heading="$section['heading']" :description="$section['description']" :theme="$sectionIsDark ? 'dark' : 'light'" />
        <div class="public-page-card-grid">@foreach($section['features'] as $feature)<x-public.card :variant="$sectionIsDark ? 'dark' : 'feature'"><span class="public-card__icon"><x-ui.icon :name="$feature['icon']" size="18" /></span>
                <h3>{{ $feature['title'] }}</h3>
                <p>{{ $feature['description'] }}</p>
            </x-public.card>@endforeach</div>
    </x-public.section>
    @endif
    @endforeach

    <x-public.section class="public-final-cta public-page-cta {{ $pageKey === 'pos' ? 'public-section--dark' : '' }}">
        <div class="public-final-cta__content"><x-public.eyebrow>Explore the workspace</x-public.eyebrow>
            @if($pageKey === 'integrations')
                <h2>Discuss an integration for your hotel.</h2>
                <p>Tell us about the provider or workflow you want to explore.</p>
                <div class="public-final-cta__actions"><x-public.button :href="route('public.contact', ['enquiry_type' => 'integrations'])" variant="primary">Contact Us</x-public.button></div>
            @else
                <h2>Explore a better flow for your hotel.</h2>
                <p>See how Lodgix can support the way your teams work.</p>
                <div class="public-final-cta__actions"><x-public.button :href="route('public.contact')" variant="primary">Talk to Lodgix</x-public.button>@if($pageKey !== 'security')<x-public.button :href="route($page['next'])" variant="secondary">{{ ['product' => 'Explore Operations', 'operations' => 'Explore POS', 'pos' => 'Explore Finance', 'finance' => 'View Pricing'][$pageKey] ?? 'Explore Product' }}</x-public.button>@endif</div>
            @endif
        </div>
    </x-public.section>
</div>
@endsection
