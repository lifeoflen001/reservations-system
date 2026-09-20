@extends('layouts.app')

@php($financialVisible = (bool) ($financialVisible ?? false))

@section('content')
<div class="report-page">
    <x-page-header title="Reports" subtitle="Revenue, occupancy, ADR, RevPAR and operational exports.">
        @can('reports.export')
            @if($financialVisible)<a class="ui-button ui-button--secondary" href="{{ route('reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"><x-ui.icon name="download" size="16" /> Export CSV</a>@endif
        @endcan
    </x-page-header>
    <form class="filter-toolbar report-filters" method="GET" action="{{ route('reports.index') }}">
        <div class="report-filter-field">
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="{{ old('from', $from->toDateString()) }}" class="form-control">
            @error('from')<small class="form-error">{{ $message }}</small>@enderror
        </div>
        <div class="report-filter-field">
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="{{ old('to', $to->toDateString()) }}" class="form-control">
            @error('to')<small class="form-error">{{ $message }}</small>@enderror
        </div>
        <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filter</button>
    </form>

    <div class="report-kpi-grid">
        <article class="report-kpi-card"><span>Revenue</span><strong>{{ $financialVisible ? $formatter->format($revenue) : '—' }}</strong></article>
        <article class="report-kpi-card"><span>Occupancy</span><strong>{{ $occupancy }}%</strong></article>
        <article class="report-kpi-card"><span>ADR</span><strong>{{ $financialVisible ? $formatter->format($adr) : '—' }}</strong></article>
        <article class="report-kpi-card"><span>RevPAR</span><strong>{{ $financialVisible ? $formatter->format($revpar) : '—' }}</strong></article>
        <article class="report-kpi-card"><span>Outstanding</span><strong>{{ $financialVisible ? $formatter->format($outstanding) : '—' }}</strong></article>
    </div>

    <div class="reports-panels">
        <x-ui.card title="Reservations by source" icon="document">
            <div class="report-bars">
                @php($sourceMax = max(1, (int) $sourceCounts->max()))
                @forelse($sourceCounts as $source => $count)
                    <div class="report-bar"><span>{{ $source }}</span><i><b style="width: {{ ((int) $count / $sourceMax) * 100 }}%"></b></i><strong>{{ $count }}</strong></div>
                @empty
                    <p class="empty-inline">No reservation source data for this period.</p>
                @endforelse
            </div>
        </x-ui.card>
        <x-ui.card title="Revenue by room type" icon="building">
            <div class="report-bars">
                @if(!$financialVisible)
                    <p class="empty-inline">Financial data is restricted for this account.</p>
                @else
                    @php($roomTypeMax = max(1, (float) $roomTypeRevenue->max()))
                    @forelse($roomTypeRevenue as $type => $amount)
                        <div class="report-bar"><span>{{ $type }}</span><i><b style="width: {{ ((float) $amount / $roomTypeMax) * 100 }}%"></b></i><strong>{{ $formatter->format($amount) }}</strong></div>
                    @empty
                        <p class="empty-inline">No room revenue for this period.</p>
                    @endforelse
                @endif
            </div>
        </x-ui.card>
    </div>

    <x-ui.card title="Reservations" icon="document" class="reports-table-card">
        <x-data.table caption="Reservations report"><thead><tr><th>Code</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Total amount</th><th>Status</th></tr></thead><tbody>
            @forelse($reservations as $reservation)
                <tr><td><a class="text-link" href="{{ route('reservations.show', $reservation) }}">{{ $reservation->code }}</a></td><td>{{ $reservation->client?->full_name }}</td><td>{{ $reservation->room?->room_number }}</td><td>{{ $reservation->check_in?->format('m/d/Y') }}</td><td>{{ $reservation->check_out?->format('m/d/Y') }}</td><td>{{ $financialVisible ? $formatter->format($reservation->total_amount) : '—' }}</td><td><x-ui.badge :variant="$reservation->status->badgeVariant()">{{ $reservation->status->label() }}</x-ui.badge></td></tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><x-ui.icon name="chart" size="28" /><strong>No reservations in this period.</strong><span>Adjust the date range to view operational activity.</span></div></td></tr>
            @endforelse
        </tbody></x-data.table>
    </x-ui.card>
</div>
@endsection
