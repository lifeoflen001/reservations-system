@if(config('hotel.tenancy.multi_property_ui') && ! request()->routeIs('website.*', 'contact-enquiries.*'))
@php
    $tenantContext = app(\App\Services\Tenancy\TenantContext::class);
    $currentOrganization = $tenantContext->currentOrganization();
    $currentProperty = $tenantContext->currentProperty();
    $memberships = $tenantContext->accessibleOrganizationMemberships();
    $currentUrl = request()->fullUrl();
    $switcherId = 'tenant-switcher-menu';
@endphp
@endif

@if(config('hotel.tenancy.multi_property_ui') && ! request()->routeIs('website.*', 'contact-enquiries.*') && $currentOrganization && $currentProperty)
<div class="tenant-switcher dropdown" data-dropdown>
    <button class="tenant-switcher__toggle" type="button" data-dropdown-toggle aria-expanded="false" aria-controls="{{ $switcherId }}" aria-label="Current property: {{ $currentProperty->name }}">
        <span class="tenant-switcher__icon"><x-ui.icon name="building" size="17" /></span>
        <span class="tenant-switcher__copy"><strong title="{{ $currentProperty->name }}">{{ $currentProperty->name }}</strong><small title="{{ $currentOrganization->name }}">{{ $currentOrganization->name }}</small></span>
        @if($memberships->sum(fn ($membership) => $membership->propertyAccess->count()) > 1 || $memberships->count() > 1)<x-ui.icon name="chevron-down" size="15" class="tenant-switcher__chevron" />@endif
    </button>
    @if($memberships->sum(fn ($membership) => $membership->propertyAccess->count()) > 1 || $memberships->count() > 1)
    <div id="{{ $switcherId }}" class="dropdown__menu tenant-switcher__menu" data-dropdown-menu hidden>
        <div class="tenant-switcher__heading"><span>Current workspace</span><strong>{{ $currentOrganization->name }}</strong></div>
        <label class="tenant-switcher__search"><x-ui.icon name="search" size="14" /><input type="search" placeholder="Search properties..." aria-label="Search properties" data-tenant-search></label>
        @foreach($memberships as $membership)
            @php($organization = $membership->organization)
            <div class="tenant-switcher__organization" data-tenant-organization>
                @if($memberships->count() > 1)
                    <span class="dropdown__label">{{ $organization?->name }}</span>
                @endif
                <div class="tenant-switcher__property-label">Properties</div>
                @foreach($membership->propertyAccess->filter(fn ($access) => $access->property)->sortBy(fn ($access) => $access->property->name) as $access)
                    <form method="POST" action="{{ route('context.property') }}" data-tenant-option data-tenant-name="{{ strtolower($access->property->name.' '.$organization?->name) }}">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $access->property->id }}"><input type="hidden" name="return_to" value="{{ $currentUrl }}">
                        <button class="tenant-switcher__property {{ (int) $currentProperty->id === (int) $access->property->id && (int) $currentOrganization->id === (int) $organization?->id ? 'is-active' : '' }}" type="submit"><span class="tenant-switcher__avatar">{{ $access->property->initials ?? collect(preg_split('/\s+/', $access->property->name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') }}</span><span><strong>{{ $access->property->name }}</strong><small>{{ $organization?->name }}</small></span>@if((int) $currentProperty->id === (int) $access->property->id && (int) $currentOrganization->id === (int) $organization?->id)<x-ui.icon name="check" size="15" />@endif</button>
                    </form>
                @endforeach
            </div>
        @endforeach
        @if($memberships->count() > 1)
            <div class="tenant-switcher__divider"></div><div class="tenant-switcher__property-label">Organizations</div>
            @foreach($memberships as $membership)
                <form method="POST" action="{{ route('context.organization') }}">
                    @csrf<input type="hidden" name="organization_id" value="{{ $membership->organization_id }}"><input type="hidden" name="return_to" value="{{ $currentUrl }}"><button class="tenant-switcher__organization-option" type="submit"><span>{{ $membership->organization?->name }}</span>@if((int) $currentOrganization->id === (int) $membership->organization_id)<x-ui.icon name="check" size="15" />@endif</button>
                </form>
            @endforeach
        @endif
    </div>
    @endif
</div>
@endif
