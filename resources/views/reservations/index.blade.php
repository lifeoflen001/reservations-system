@extends('layouts.app')

@php($formatter = app(\App\Support\CurrencyFormatter::class))

@section('content')
    <x-page-header title="Reservations" subtitle="Manage bookings, arrivals, departures and balances.">
        <button type="button" class="ui-button ui-button--secondary" data-print><x-ui.icon name="print" size="16" /> Print</button>
        @can('export', \App\Models\Reservation::class)
            <a class="ui-button ui-button--secondary" href="{{ route('reservations.export', request()->query()) }}"><x-ui.icon name="download" size="16" /> Export CSV</a>
        @endcan
        @can('create', \App\Models\Reservation::class)<a class="ui-button ui-button--primary" href="{{ route('reservations.create') }}"><x-ui.icon name="plus" size="16" /> New reservation</a>@endcan
    </x-page-header>

    <x-kpi-grid :items="$kpis" />

    @if ($errors->any())<x-feedback.alert type="danger" class="page-feedback">{{ $errors->first() }}</x-feedback.alert>@endif
    <form class="filter-toolbar reservation-filters" method="GET" action="{{ route('reservations.index') }}">
        <x-form.input name="search" value="{{ request('search') }}" placeholder="Search" aria-label="Search reservations" field-class="reservation-filter-search" />
        <x-form.select name="status" aria-label="Filter by status"><option value="all">All</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status', 'all') === $status->value)>{{ $status->label() }}</option>@endforeach</x-form.select>
        <x-form.input name="from" type="date" value="{{ request('from') }}" aria-label="From date" />
        <x-form.input name="to" type="date" value="{{ request('to') }}" aria-label="To date" />
        <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filter</button>
        <a class="ui-button ui-button--secondary" href="{{ route('reservations.index') }}">Reset</a>
    </form>

    <section class="ui-card reservations-card">
        <x-data.table caption="Reservations">
            <thead><tr><th>Code</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Source</th><th>Total amount</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse ($reservations as $reservation)
                @php($paid = (float) ($reservation->paid_amount ?? 0))
                <tr>
                    <td><a class="text-link reservation-code" href="{{ route('reservations.show', $reservation) }}">{{ $reservation->code }}</a></td>
                    <td><strong>{{ $reservation->client->full_name }}</strong><small class="table-muted">{{ $reservation->client->email }}</small></td>
                    <td><strong>{{ $reservation->room->room_number }}</strong><small class="table-muted">{{ $reservation->room->roomType?->name ?? 'Room' }}</small></td>
                    <td>{{ $reservation->check_in->format('m/d/Y') }}</td>
                    <td>{{ $reservation->check_out->format('m/d/Y') }}</td>
                    <td>{{ $reservation->source?->name ?? '—' }}</td>
                    <td>{{ $formatter->format($reservation->total_amount) }}</td>
                    <td class="{{ ((float) $reservation->total_amount - $paid) > 0 ? 'balance-due' : 'balance-paid' }}">{{ $formatter->format(max(0, (float) $reservation->total_amount - $paid)) }}</td>
                    <td><x-ui.badge :variant="$reservation->status->badgeVariant()">{{ $reservation->status->label() }}</x-ui.badge></td>
                    <td><div class="row-actions">
                        <a class="icon-button" aria-label="View reservation" data-tooltip="View" href="{{ route('reservations.show', $reservation) }}"><x-ui.icon name="eye" size="17" /></a>
                        @can('checkIn', $reservation) @if($reservation->status === \App\Enums\ReservationStatus::Confirmed)<form method="POST" action="{{ route('reservations.check-in', $reservation) }}">@csrf<button class="icon-button icon-button--success" type="submit" aria-label="Check in" data-tooltip="Check in"><x-ui.icon name="check" size="17" /></button></form>@endif @endcan
                        @can('checkOut', $reservation) @if($reservation->status === \App\Enums\ReservationStatus::CheckedIn)<form method="POST" action="{{ route('reservations.check-out', $reservation) }}">@csrf<button class="icon-button icon-button--primary" type="submit" aria-label="Check out" data-tooltip="Check out"><x-ui.icon name="arrow-right" size="17" /></button></form>@endif @endcan
                        @can('update', $reservation) @if(! in_array($reservation->status, [\App\Enums\ReservationStatus::Cancelled, \App\Enums\ReservationStatus::NoShow], true))<a class="icon-button" aria-label="Edit reservation" data-tooltip="Edit" href="{{ route('reservations.edit', $reservation) }}"><x-ui.icon name="edit" size="17" /></a>@endif @endcan
                        @can('delete', $reservation) @if(! $reservation->payments_exists)<form method="POST" action="{{ route('reservations.destroy', $reservation) }}" data-confirm="Remove this reservation from the active list?">@csrf @method('DELETE')<button class="icon-button icon-button--danger" type="submit" aria-label="Delete reservation" data-tooltip="Delete"><x-ui.icon name="trash" size="17" /></button></form>@endif @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="empty-state"><x-ui.icon name="calendar" size="28" /><strong>{{ request()->hasAny(['search', 'status', 'from', 'to']) ? 'No reservations match the selected filters.' : 'No reservations found.' }}</strong><span>Create a reservation to start tracking stays and room availability.</span></div></td></tr>
            @endforelse
            </tbody>
        </x-data.table>
        <x-data.pagination :paginator="$reservations" />
    </section>

    @can('create', \App\Models\Reservation::class)
        @include('reservations.partials.form-modal', ['reservation' => null, 'open' => $openNew, 'clients' => $clients, 'rooms' => $rooms, 'sources' => $sources, 'statuses' => $statuses, 'defaultCheckInTime' => $defaultCheckInTime, 'defaultCheckOutTime' => $defaultCheckOutTime])
    @endcan
    @can('update', $editReservation ?? new \App\Models\Reservation)
        @if($editReservation) @include('reservations.partials.form-modal', ['reservation' => $editReservation, 'open' => true, 'clients' => $clients, 'rooms' => $rooms, 'sources' => $sources, 'statuses' => $statuses, 'defaultCheckInTime' => $defaultCheckInTime, 'defaultCheckOutTime' => $defaultCheckOutTime]) @endif
    @endcan
    @if($openReservation) @include('reservations.partials.details-modal', ['reservation' => $openReservation, 'formatter' => $formatter]) @endif
@endsection
