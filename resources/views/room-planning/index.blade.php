@extends('layouts.app')

@php($formatter = app(\App\Support\CurrencyFormatter::class))
@php($query = request()->query())

@section('content')
    <x-page-header title="Room Planning" subtitle="Live room availability, occupancy and reservation timeline.">
        @can('create', \App\Models\Reservation::class)<a class="ui-button ui-button--primary" href="{{ route('reservations.create') }}"><x-ui.icon name="plus" size="16" /> New reservation</a>@endcan
    </x-page-header>

    <form class="planning-filter-bar" method="GET" action="{{ route('room-planning.index') }}">
        <input type="hidden" name="view" value="{{ $view }}">
        <x-form.input name="search" value="{{ request('search') }}" placeholder="Search room or type" aria-label="Search room or type" />
        <x-form.input name="reservation_search" value="{{ request('reservation_search') }}" placeholder="Guest or reservation code" aria-label="Search guest or reservation code" />
        <x-form.select name="floor_id" aria-label="Filter by floor"><option value="all">All floors</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected((string) request('floor_id', 'all') === (string) $floor->id)>{{ $floor->name }}</option>@endforeach</x-form.select>
        <x-form.select name="category_id" aria-label="Filter by category"><option value="all">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id', 'all') === (string) $category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
        <x-form.select name="room_type_id" aria-label="Filter by room type"><option value="all">All room types</option>@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}" @selected((string) request('room_type_id', 'all') === (string) $roomType->id)>{{ $roomType->name }}</option>@endforeach</x-form.select>
        <x-form.select name="status" aria-label="Filter by operational status"><option value="all">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status', 'all') === $status->value)>{{ $status->label() }}</option>@endforeach</x-form.select>
        <x-form.select name="reservation_status" aria-label="Filter by reservation status"><option value="all">All reservation statuses</option>@foreach($reservationStatuses as $reservationStatus)<option value="{{ $reservationStatus->value }}" @selected(request('reservation_status', 'all') === $reservationStatus->value)>{{ $reservationStatus->label() }}</option>@endforeach</x-form.select>
        <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filter</button>
        <a class="ui-button ui-button--secondary" href="{{ route('room-planning.index', ['view' => $view]) }}">Reset</a>
    </form>

    <form class="planning-toolbar" method="GET" action="{{ route('room-planning.index') }}">
        @foreach(request()->except(['start', 'view', 'days']) as $key => $value)
            @if(is_array($value)) @foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <nav class="planning-view-switcher" aria-label="Calendar view">
            @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'two_months' => '2 Months'] as $key => $label)
                <a class="planning-view-switcher__item {{ $view === $key ? 'is-active' : '' }}" href="{{ route('room-planning.index', array_merge(request()->except(['start', 'view', 'days']), ['view' => $key])) }}"><x-ui.icon name="calendar" size="15" /> {{ $label }}</a>
            @endforeach
        </nav>
        <div class="planning-toolbar__spacer"></div>
        <span class="planning-range" aria-live="polite"><small>Showing</small> {{ $start->format('d M Y') }} — {{ $rangeEnd->format('d M Y') }}</span>
        <input class="form-control planning-date" type="date" name="start" value="{{ $start->format('Y-m-d') }}" aria-label="Planning start date">
        <a class="ui-button ui-button--secondary planning-today" href="{{ route('room-planning.index', array_merge(request()->except(['start', 'days']), ['view' => $view])) }}"><x-ui.icon name="target" size="15" /> Today</a>
        <a class="ui-button ui-button--secondary planning-nav-button" href="{{ route('room-planning.index', array_merge($query, ['view' => $view, 'start' => $start->copy()->subDays($periodDays * 2)->format('Y-m-d')])) }}" aria-label="Previous large period" data-tooltip="Previous large period"><x-ui.icon name="chevron-right" size="16" class="icon-rotate-180" /><span>Previous</span></a>
        <a class="ui-button ui-button--secondary planning-nav-button" href="{{ route('room-planning.index', array_merge($query, ['view' => $view, 'start' => $start->copy()->subDays($periodDays)->format('Y-m-d')])) }}" aria-label="Previous period" data-tooltip="Previous period"><x-ui.icon name="chevron-right" size="16" class="icon-rotate-180" /><span>Back</span></a>
        <a class="ui-button ui-button--secondary planning-nav-button" href="{{ route('room-planning.index', array_merge($query, ['view' => $view, 'start' => $start->copy()->addDays($periodDays)->format('Y-m-d')])) }}" aria-label="Next period" data-tooltip="Next period"><span>Next</span><x-ui.icon name="chevron-right" size="16" /></a>
        <a class="ui-button ui-button--secondary planning-nav-button" href="{{ route('room-planning.index', array_merge($query, ['view' => $view, 'start' => $start->copy()->addDays($periodDays * 2)->format('Y-m-d')])) }}" aria-label="Next large period" data-tooltip="Next large period"><span>Next</span><x-ui.icon name="chevron-right" size="16" /></a>
        <button class="icon-button" type="button" data-planning-refresh="{{ route('room-planning.data', request()->query()) }}" aria-label="Refresh room planning" data-tooltip="Refresh room planning"><x-ui.icon name="refresh" size="16" /></button>
    </form>

    <section class="ui-card planning-card" data-planning-card aria-live="polite">
        <div class="planning-feedback" data-planning-feedback hidden role="status"></div>
        <div class="planning-skeleton" data-planning-skeleton hidden aria-hidden="true">
            <div class="planning-skeleton__header"><x-ui.skeleton class="planning-skeleton__corner" />@for($column = 0; $column < min($periodDays, 12); $column++)<x-ui.skeleton class="planning-skeleton__day" />@endfor</div>
            @for($row = 0; $row < 7; $row++)<div class="planning-skeleton__row"><x-ui.skeleton class="planning-skeleton__room" /><x-ui.skeleton class="planning-skeleton__track" /></div>@endfor
        </div>
        <div class="planning-scroll">
            <div class="planning-grid" style="--planning-days: {{ $periodDays }}">
                <div class="planning-corner planning-corner--week" aria-hidden="true"></div>
                <div class="planning-week-header">
                    @foreach($weekGroups as $week)<strong style="--week-span: {{ $week['span'] }}">{{ $week['label'] }}</strong>@endforeach
                </div>
                <div class="planning-corner planning-corner--days">Room</div>
                @foreach($days as $day)<div class="planning-date-cell {{ $day->isToday() ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}"><small>{{ $day->format('D') }}</small><strong>{{ $day->format('j') }}</strong></div>@endforeach

                @php($groupedRooms = $rooms->groupBy(fn ($room) => $room->category?->name ?? 'Uncategorized'))
                @forelse($groupedRooms as $categoryName => $categoryRooms)
                    @php($groupKey = Str::slug($categoryName))
                    @php($categoryColor = $categoryRooms->first()->category?->color ?: '#69a7e8')
                    <div class="planning-group-label" style="--category-color: {{ $categoryColor }}"><strong><button class="planning-group-toggle" type="button" data-planning-group-toggle="{{ $groupKey }}" aria-expanded="true" aria-label="Collapse {{ $categoryName }}" data-tooltip="Collapse {{ $categoryName }}">▾</button><i class="planning-category-swatch"></i>{{ $categoryName }}</strong></div>
                    <div class="planning-group-timeline" data-planning-group-row="{{ $groupKey }}" aria-label="Available rooms in {{ $categoryName }} by day">
                        @foreach($days as $dayIndex => $day)<span class="planning-availability-cell {{ $day->isToday() ? 'is-today' : '' }}" title="{{ $availabilityByCategory[$categoryName][$dayIndex] }} available on {{ $day->format('d M Y') }}">{{ $availabilityByCategory[$categoryName][$dayIndex] }}</span>@endforeach
                    </div>
                    @foreach($categoryRooms->groupBy(fn ($room) => $room->roomType?->name ?? 'Room') as $typeName => $typeRooms)
                        <div class="planning-type-label" data-planning-group-row="{{ $groupKey }}"><strong>{{ $typeName }}</strong></div>
                        <div class="planning-type-timeline" data-planning-group-row="{{ $groupKey }}"></div>
                        @foreach($typeRooms as $room)
                            <div class="planning-room-label" style="--category-color: {{ $categoryColor }}" data-planning-group-row="{{ $groupKey }}" title="Room {{ $room->room_number }} · {{ $room->floor?->name ?? 'No floor' }} · {{ ucfirst($room->housekeeping_status->value) }}" aria-label="Room {{ $room->room_number }}, {{ $room->floor?->name ?? 'No floor' }}, {{ ucfirst($room->housekeeping_status->value) }}">
                                <div><span class="planning-status-dot planning-status-dot--{{ $room->operational_status->value }}"></span><strong>{{ $room->room_number }}</strong></div>
                            </div>
                            <div class="planning-timeline" data-planning-group-row="{{ $groupKey }}">
                                @foreach($days as $day)
                                    @can('create', \App\Models\Reservation::class)
                                        <a class="planning-day-cell planning-day-cell--action {{ $day->isToday() ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}" href="{{ route('reservations.index', ['new' => 1, 'room_id' => $room->id, 'check_in' => $day->format('Y-m-d'), 'check_out' => $day->copy()->addDay()->format('Y-m-d')]) }}" aria-label="Create reservation for room {{ $room->room_number }} on {{ $day->format('d M Y') }}"></a>
                                    @else
                                        <i class="planning-day-cell {{ $day->isToday() ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}"></i>
                                    @endcan
                                @endforeach
                                @foreach($blocksByRoom->get($room->id, collect()) as $block)
                                    <span class="planning-period planning-period--{{ $block['type'] }}" style="--bar-offset: {{ $block['offset'] }}; --bar-span: {{ $block['span'] }}" title="{{ $block['label'] ?: ucfirst($block['type']) }}"><x-ui.icon name="{{ $block['type'] === 'maintenance' ? 'wrench' : 'lock' }}" size="13" /> {{ $block['label'] ?: ucfirst($block['type']) }}</span>
                                @endforeach
                                @foreach($bars->get($room->id, collect()) as $bar)
                                    @php($reservation = $bar['reservation'])
                                    @php($balance = max(0, (float) $reservation->total_amount - (float) ($reservation->paid_amount ?? 0)))
                                    @php($tooltip = sprintf('%s · %s | Room %s | %s to %s | %d nights | %s', $reservation->client->full_name, $reservation->code, $room->room_number, $reservation->check_in->format('d M Y'), $reservation->check_out->format('d M Y'), $reservation->nights(), $reservation->status->label()))
                                    @if($canViewFinancials) @php($tooltip .= sprintf(' | Balance: %s', $formatter->format($balance))) @endif
                                    <a class="planning-bar planning-bar--{{ $reservation->status->value }} {{ in_array($reservation->id, $conflictingReservationIds, true) ? 'planning-bar--conflict' : '' }}" href="{{ route('reservations.show', $reservation) }}" style="--bar-offset: {{ $bar['offset'] }}; --bar-span: {{ $bar['span'] }}; --category-color: {{ $categoryColor }}" title="{{ $conflictingReservationIds && in_array($reservation->id, $conflictingReservationIds, true) ? 'Reservation conflict detected. ' : '' }}{{ $tooltip }}" aria-label="Reservation {{ $reservation->code }}, {{ $reservation->client->full_name }}, Room {{ $room->room_number }}, {{ $reservation->check_in->format('d F Y') }} to {{ $reservation->check_out->format('d F Y') }}, {{ $reservation->status->label() }}">
                                        <span class="planning-bar__code">{{ $reservation->code }}</span><span class="planning-bar__guest">{{ $reservation->client->full_name }}</span>
                                        <span class="planning-bar__popover" role="tooltip"><strong>{{ $reservation->client->full_name }}</strong><span>{{ $reservation->code }} · Room {{ $room->room_number }} · {{ $room->roomType?->name ?? 'Room' }}</span><span>{{ $reservation->check_in->format('d M Y, H:i') }} → {{ $reservation->check_out->format('d M Y, H:i') }}</span><span>{{ $reservation->nights() }} {{ Str::plural('night', $reservation->nights()) }} · {{ $reservation->adults }} adults · {{ $reservation->children }} children</span><span class="planning-bar__popover-status">{{ $reservation->status->label() }}</span>@if($conflictingReservationIds && in_array($reservation->id, $conflictingReservationIds, true))<span class="planning-bar__popover-warning">Reservation conflict detected.</span>@endif @if($canViewFinancials)<span>Paid: {{ $formatter->format($reservation->paid_amount ?? 0) }} · Balance: {{ $formatter->format($balance) }}</span>@endif</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                @empty
                    <div class="planning-empty"><x-ui.icon name="bed" size="26" /><strong>No rooms match the selected filters.</strong><span>Try clearing a filter or add an active room.</span></div>
                @endforelse
            </div>
        </div>
        <div class="planning-legend">
            <span><i class="planning-legend__swatch planning-legend__swatch--confirmed"></i> Confirmed</span><span><i class="planning-legend__swatch planning-legend__swatch--checked_in"></i> Checked in</span><span><i class="planning-legend__swatch planning-legend__swatch--pending"></i> Pending</span><span><i class="planning-legend__swatch planning-legend__swatch--maintenance"></i> Maintenance</span><span><i class="planning-legend__swatch planning-legend__swatch--blocked"></i> Blocked</span>
        </div>
    </section>
@endsection
