@extends('layouts.app')

@php
    $property ??= null;
    $theme = old('theme', $settings['theme'] ?? 'light');
    $sections = [
        'general' => ['General', 'settings'], 'sources' => ['Reservation sources', 'calendar'], 'database' => ['Database', 'database'],
        'license' => ['License', 'shield'], 'updates' => ['Updates', 'download'], 'security' => ['Security', 'key'], 'about' => ['About', 'building'], 'integrations' => ['Integrations', 'plug'],
    ];
@endphp
@section('content')
<x-page-header title="Settings" subtitle="Property, interface, database, license and update configuration." />
<div class="settings-layout">
    <nav class="settings-nav" aria-label="Settings sections">
        @foreach($sections as $key => [$label, $icon])
            <a class="settings-nav__item {{ $section === $key ? 'is-active' : '' }}" href="{{ route('settings.index', ['section' => $key]) }}"><x-ui.icon :name="$icon" size="18" /><span>{{ $label }}</span></a>
        @endforeach
    </nav>
    <div class="settings-content">
        @if($section === 'general')
            <x-ui.card title="General" icon="building">
                <form method="POST" action="{{ route('settings.general.update') }}" data-draft-form data-draft-key="settings-general">
                    @csrf @method('PUT')
                    <div class="settings-form-grid">
                        <x-form.input name="name" label="Property name" :value="$property?->name ?? config('hotel.defaults.property_name')" required field-class="form-field--full" />
                        <x-form.input name="email" type="email" label="Email" :value="$property?->email" />
                        <x-form.input name="phone" label="Phone" :value="$property?->phone" />
                        <x-form.textarea name="address" label="Address" :value="$property?->address" field-class="form-field--full" rows="2" />
                        <x-form.input name="city" label="City" :value="$property?->city" />
                        <x-form.input name="country" label="Country" :value="$property?->country" />
                        <div class="form-field"><label for="currency_display">Currency</label><input id="currency_display" class="form-control" value="{{ $currency->code }} · {{ $currency->name }}" disabled><small class="form-help">Currency can only be changed through the setup wizard, with conversion of existing amounts.</small></div>
                        <x-form.select name="default_language" label="Language" required><option value="">Select language</option>@foreach($languages as $language)<option value="{{ $language->code }}" @selected(old('default_language', $property?->default_language ?? 'en') === $language->code)>{{ $language->name }}</option>@endforeach</x-form.select>
                        <x-form.select name="theme" label="Theme" required><option value="light" @selected($theme === 'light')>Light</option><option value="dark" @selected($theme === 'dark')>Dark</option><option value="system" @selected($theme === 'system')>System</option></x-form.select>
                        <x-form.select name="timezone" label="Property timezone" required><option value="">Select timezone</option>@foreach(\DateTimeZone::listIdentifiers() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $property?->timezone ?? config('hotel.defaults.timezone')) === $timezone)>{{ $timezone }}</option>@endforeach</x-form.select>
                        <x-form.input name="check_in_time" type="time" label="Check-in" :value="old('check_in_time', $property?->check_in_time ? substr((string) $property->check_in_time, 0, 5) : config('hotel.defaults.check_in_time'))" required />
                        <x-form.input name="check_out_time" type="time" label="Check-out" :value="old('check_out_time', $property?->check_out_time ? substr((string) $property->check_out_time, 0, 5) : config('hotel.defaults.check_out_time'))" required />
                    </div>
                    <div class="settings-form-footer"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div>
                </form>
            </x-ui.card>
        @elseif($section === 'sources')
            <x-ui.card title="Reservation sources" icon="calendar"><x-slot:header><a class="ui-button ui-button--primary" href="{{ route('settings.index', ['section' => 'sources', 'new_source' => 1]) }}"><x-ui.icon name="plus" size="16" /> New source</a></x-slot:header>
                <p class="settings-lead">Manage the channels used to attribute bookings. Inactive sources remain available on historical reservations.</p>
                <x-data.table caption="Reservation sources"><thead><tr><th>Name</th><th>Code</th><th>Description</th><th>Status</th><th>Reservations</th><th class="table-actions">Actions</th></tr></thead><tbody>@forelse($sources as $item)<tr><td><strong>{{ $item->name }}</strong></td><td><code>{{ $item->code }}</code></td><td>{{ $item->description ?: '—' }}</td><td><x-ui.badge :variant="$item->is_active ? 'success' : 'neutral'">{{ $item->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td><td>{{ $item->reservations_count }}</td><td class="table-actions"><div class="row-actions"><a class="icon-button" href="{{ route('settings.index', ['section' => 'sources', 'edit_source' => $item->id]) }}" aria-label="Edit source"><x-ui.icon name="edit" size="17" /></a><form method="POST" action="{{ route('settings.sources.toggle', $item) }}">@csrf<button class="icon-button" type="submit" aria-label="Toggle source"><x-ui.icon name="refresh" size="17" /></button></form>@if(!$item->reservations_count)<form method="POST" action="{{ route('settings.sources.destroy', $item) }}" data-confirm="Delete this reservation source?" data-confirm-title="Delete reservation source" data-confirm-label="Delete">@csrf @method('DELETE')<button class="icon-button icon-button--danger" type="submit" aria-label="Delete source"><x-ui.icon name="trash" size="17" /></button></form>@endif</div></td></tr>@empty<tr><td colspan="6">No reservation sources configured.</td></tr>@endforelse</tbody></x-data.table>
            </x-ui.card>
            @if($openSourceForm)<x-ui.modal id="source-form" :title="$source ? 'Edit reservation source' : 'New reservation source'" open="true"><form method="POST" action="{{ $source ? route('settings.sources.update', $source) : route('settings.sources.store') }}" data-draft-form data-draft-key="reservation-source-{{ $source?->id ?? 'new' }}">@csrf @if($source) @method('PUT') @endif<x-form.input name="name" label="Name" :value="$source?->name" required /><x-form.input name="code" label="Code" :value="$source?->code" placeholder="booking-com" required /><x-form.textarea name="description" label="Description" rows="3" :value="$source?->description" /><x-form.input name="sort_order" type="number" label="Display order" :value="$source?->sort_order ?? 0" /><label class="check-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $source?->is_active ?? true))> Active</label><div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('settings.index', ['section' => 'sources']) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div></form></x-ui.modal>@endif
        @elseif($section === 'integrations')
            <x-ui.card title="Integrations & API" icon="plug"><p class="settings-lead">Configure outbound email, provider readiness, scoped API tokens and signed webhooks. Credentials are encrypted at rest and never displayed.</p>
                @if($oneTimeToken)<div class="alert alert--warning"><strong>Copy this API token now:</strong> <code>{{ $oneTimeToken }}</code><br><small>It will not be shown again.</small></div>@endif
                <div class="settings-integration-grid"><div><strong>Email / SMTP</strong><x-ui.badge :variant="$emailIntegration?->status === 'configured' ? 'success' : 'neutral'">{{ $emailIntegration?->status ?? 'Not configured' }}</x-ui.badge><form method="POST" action="{{ route('settings.integrations.email') }}" data-draft-form data-draft-key="integration-email">@csrf<x-form.input name="host" label="SMTP host" :value="$emailIntegration?->settings['host'] ?? ''" required /><div class="settings-form-grid"><x-form.input name="port" type="number" label="Port" :value="$emailIntegration?->settings['port'] ?? 587" required /><x-form.select name="encryption" label="Encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></x-form.select></div><x-form.input name="username" label="Username" :value="$emailIntegration?->settings['username'] ?? ''" /><x-form.input name="password" type="password" label="Password" placeholder="Leave blank to keep current secret" /><div class="settings-form-grid"><x-form.input name="from_email" type="email" label="From email" :value="$emailIntegration?->settings['from_email'] ?? ''" required /><x-form.input name="from_name" label="From name" :value="$emailIntegration?->settings['from_name'] ?? config('hotel.brand.name')" required /></div><label class="check-field"><input type="checkbox" name="enabled" value="1" @checked($emailIntegration?->is_enabled)> Enabled</label><div class="row-actions"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save email</button></form><form method="POST" action="{{ route('settings.integrations.email.test') }}">@csrf<button class="ui-button ui-button--info" type="submit">Test configuration</button></form></div></div>
                <div><strong>WhatsApp readiness</strong><x-ui.badge :variant="$whatsappIntegration?->status === 'configured' ? 'success' : 'neutral'">{{ $whatsappIntegration?->status ?? 'Not configured' }}</x-ui.badge><form method="POST" action="{{ route('settings.integrations.whatsapp') }}" data-draft-form data-draft-key="integration-whatsapp">@csrf<x-form.input name="provider" label="Provider" :value="$whatsappIntegration?->provider ?? 'cloud_api'" required /><x-form.input name="phone_number_id" label="Phone number ID" :value="$whatsappIntegration?->settings['phone_number_id'] ?? ''" /><x-form.input name="api_base_url" label="API base URL" :value="$whatsappIntegration?->settings['api_base_url'] ?? ''" /><x-form.input name="access_token" type="password" label="Access token" placeholder="Leave blank to keep current secret" /><label class="check-field"><input type="checkbox" name="enabled" value="1" @checked($whatsappIntegration?->is_enabled)> Enabled</label><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save WhatsApp</button></form><hr><strong>Payment gateways</strong><p class="settings-lead">{{ $gatewayIntegrations->count() }} gateway connection(s). Callbacks require signed provider requests and are idempotent.</p><strong>Booking channels</strong><p class="settings-lead">{{ $channels->count() }} channel connection(s). No provider is marked connected until credentials and a verified test exist.</p></div></div>
            </x-ui.card>
            <x-ui.card title="API tokens" icon="key"><p class="settings-lead">Tokens are scoped, hashed and shown only once.</p><form method="POST" action="{{ route('settings.integrations.api-tokens.store') }}" class="settings-token-form">@csrf<x-form.input name="name" label="Token name" required /><div class="settings-token-scopes">@foreach(['rooms:read','availability:read','reservations:read','reservations:write','clients:read','payments:read'] as $ability)<label class="check-field"><input type="checkbox" name="abilities[]" value="{{ $ability }}"> {{ $ability }}</label>@endforeach</div><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="plus" size="16" /> Create token</button></form><x-data.table caption="API tokens"><thead><tr><th>Name</th><th>Prefix</th><th>Scopes</th><th>Last used</th><th></th></tr></thead><tbody>@forelse($apiTokens as $token)<tr><td>{{ $token->name }}</td><td><code>{{ $token->token_prefix }}…</code></td><td>{{ implode(', ', $token->abilities ?? []) }}</td><td>{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td><td><form method="POST" action="{{ route('settings.integrations.api-tokens.revoke', $token) }}">@csrf<button class="icon-button icon-button--danger" data-confirm="Revoke this API token?" data-confirm-title="Revoke token" data-confirm-label="Revoke" type="submit" aria-label="Revoke token"><x-ui.icon name="trash" size="17" /></button></form></td></tr>@empty<tr><td colspan="5">No tokens issued.</td></tr>@endforelse</tbody></x-data.table></x-ui.card>
            <x-ui.card title="Outgoing webhooks" icon="share"><p class="settings-lead">Outbound deliveries are signed and retried through the queue. Localhost and local-only URLs are rejected.</p><form method="POST" action="{{ route('settings.integrations.webhooks.store') }}" class="settings-form-grid">@csrf<x-form.input name="name" label="Name" required /><x-form.input name="url" type="url" label="HTTPS endpoint" required /><x-form.input name="signing_secret" label="Signing secret" help="Minimum 16 characters; leave blank to generate." /><x-form.input name="events" label="Events (comma separated)" placeholder="reservation.created,payment.received" /><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="plus" size="16" /> Add endpoint</button></form><x-data.table caption="Webhook endpoints"><thead><tr><th>Name</th><th>URL</th><th>Events</th><th>Status</th></tr></thead><tbody>@forelse($webhooks as $webhook)<tr><td>{{ $webhook->name }}</td><td>{{ $webhook->url }}</td><td>{{ implode(', ', $webhook->events ?? []) }}</td><td><x-ui.badge :variant="$webhook->is_active ? 'success' : 'neutral'">{{ $webhook->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td></tr>@empty<tr><td colspan="4">No webhook endpoints configured.</td></tr>@endforelse</tbody></x-data.table></x-ui.card>
        @elseif($section === 'security')
            <x-ui.card title="Security" icon="key"><p class="settings-lead">Central authentication and session controls. Passwords are always hashed and never displayed or stored here.</p><form method="POST" action="{{ route('settings.security.update') }}" data-draft-form data-draft-key="settings-security">@csrf @method('PUT')<div class="settings-form-grid"><x-form.input name="password_min_length" type="number" label="Minimum password length" :value="$settings['password_min_length']" min="6" max="128" required /><x-form.input name="session_timeout" type="number" label="Session timeout (minutes)" :value="$settings['session_timeout']" min="5" max="43200" required /></div><div class="security-options"><label class="check-field"><input type="checkbox" name="password_require_mixed_case" value="1" @checked(old('password_require_mixed_case', $settings['password_require_mixed_case']))> Require mixed case</label><label class="check-field"><input type="checkbox" name="password_require_numbers" value="1" @checked(old('password_require_numbers', $settings['password_require_numbers']))> Require a number</label><label class="check-field"><input type="checkbox" name="password_require_symbols" value="1" @checked(old('password_require_symbols', $settings['password_require_symbols']))> Require a symbol</label></div><div class="settings-form-footer"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div></form></x-ui.card>
        @elseif($section === 'database')
            <x-ui.card title="Current database" icon="database">
                <x-slot:header><x-ui.badge variant="success">Active</x-ui.badge></x-slot:header>
                <div class="settings-snapshot-table">
                    <div><span>Driver</span><strong>{{ $databaseInfo['driver'] }}</strong></div>
                    <div><span>Location</span><strong class="settings-value--break">{{ $databaseInfo['location'] }}</strong></div>
                    @foreach($databaseCounts as $label => $count)
                        <div><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                    @endforeach
                </div>
                <div class="settings-action-bar">
                    <label class="check-field"><input type="checkbox" disabled> Create backup</label>
                    <a class="ui-button ui-button--info" href="{{ route('settings.index', ['section' => 'general']) }}"><x-ui.icon name="settings" size="16" /> Reconfigure system</a>
                </div>
                <details class="settings-secondary-details">
                    <summary>Database maintenance</summary>
                    <h3>Database backup</h3>
                    <p class="settings-lead">Create and download a complete SQL backup of the active database. Backups are stored locally so recent files remain visible here.</p>
                    <p class="settings-lead">Connection: {{ $databaseInfo['status'] }} · Migrations: {{ $databaseInfo['migrations'] }} · Cache: {{ $databaseInfo['cache_driver'] }} · Storage writable: {{ $databaseInfo['storage_writable'] ? 'Yes' : 'No' }}</p>
                    <div class="database-backup-history">
                        <div class="database-backup-history__heading"><h3>Recent backups</h3><span>{{ count($backups) }} {{ Str::plural('backup', count($backups)) }}</span></div>
                        <x-data.table caption="Recent database backups"><thead><tr><th>File</th><th>Created</th><th>Size</th><th class="table-actions">Actions</th></tr></thead><tbody>@forelse($backups as $backup)<tr><td><code>{{ $backup['name'] }}</code></td><td>{{ $backup['created_at']->format('m/d/Y, h:i A') }}</td><td>{{ number_format($backup['size'] / 1024, 1) }} KB</td><td class="table-actions"><a class="icon-button" href="{{ route('settings.database.backups.download', ['filename' => $backup['name']]) }}" aria-label="Download {{ $backup['name'] }}" data-tooltip="Download"><x-ui.icon name="download" size="17" /></a></td></tr>@empty<tr><td colspan="4"><div class="catalog-empty">No backups created yet.</div></td></tr>@endforelse</tbody></x-data.table>
                    </div>
                </details>
            </x-ui.card>
        @elseif($section === 'license')
            <x-ui.card title="License" icon="shield"><div class="settings-hero"><x-ui.icon name="shield" size="28" /><div><h2>{{ $edition }}</h2><p>One HotelDesk license covers the application and its configured integrations.</p></div></div><div class="settings-info-grid"><div><small>Edition</small><strong>{{ $edition }}</strong></div><div><small>License status</small><strong>Development</strong></div><div><small>Product version</small><strong>v{{ $version }}</strong></div></div></x-ui.card>
        @elseif($section === 'updates')
            <x-ui.card title="Updates" icon="download">
                <form id="settings-updates-form" method="POST" action="{{ route('settings.updates.update') }}" data-draft-form data-draft-key="settings-updates">
                    @csrf @method('PUT')
                    <x-form.input name="update_url" label="Update URL" type="url" :value="$updateInfo['url']" required field-class="form-field--full" />
                    <label class="check-field settings-check-field"><input type="checkbox" name="updates_auto_check" value="1" @checked($updateInfo['auto_check'])> Check automatically for updates</label>
                    <div class="settings-snapshot-table settings-snapshot-table--updates">
                        <div><span>Current version</span><strong>{{ $updateInfo['version'] }}</strong></div>
                        <div><span>Feed route</span><strong class="settings-value--break">{{ $updateInfo['feed_route'] }}</strong></div>
                        <div><span>Isolation</span><strong>{{ $updateInfo['isolation'] }}</strong></div>
                        <div><span>Status</span><strong>{{ $updateInfo['status'] }}</strong></div>
                        <div><span>Packaged</span><strong>{{ $updateInfo['packaged'] ? 'Yes' : 'No' }}</strong></div>
                    </div>
                </form>
                <div class="settings-form-footer settings-form-footer--left"><button class="ui-button ui-button--primary" type="submit" form="settings-updates-form"><x-ui.icon name="save" size="16" /> Save</button><form method="POST" action="{{ route('settings.updates.check') }}">@csrf<button class="ui-button ui-button--info" type="submit"><x-ui.icon name="refresh" size="16" /> Check for updates</button></form></div>
            </x-ui.card>
        @else
            <x-ui.card title="{{ $aboutInfo['title'] }}" icon="building">
                <div class="settings-hero"><span class="app-logo__mark"><x-ui.icon name="building" size="24" /></span><div><h2>{{ $aboutInfo['title'] }}</h2><p>{{ $aboutInfo['description'] }}</p></div></div>
                <div class="settings-snapshot-table settings-snapshot-table--about">
                    @foreach(['Version' => 'version', 'Electron' => 'electron', 'Node.js' => 'node', 'Chromium' => 'chromium', 'Platform' => 'platform', 'Edition' => 'edition', 'Data directory' => 'data_directory', 'Update server' => 'update_server'] as $label => $key)
                        <div><span>{{ $label }}</span><strong class="settings-value--break">{{ $aboutInfo[$key] }}</strong></div>
                    @endforeach
                </div>
                <p class="settings-about-footer">© {{ now()->year }} {{ $aboutInfo['name'] }} / ScriptExpert. SQLite-first architecture with optional MySQL migration, edition-isolated updates and multilingual user interface.</p>
            </x-ui.card>
        @endif
    </div>
</div>
@endsection
