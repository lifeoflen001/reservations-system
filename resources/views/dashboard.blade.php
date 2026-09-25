@extends('layouts.app')

@section('content')
    @php
        $chartItems = $chart['items'];
        $plotLeft = 44;
        $plotRight = 748;
        $plotTop = 18;
        $plotBottom = 218;
        $plotHeight = $plotBottom - $plotTop;
        $maxValue = max(1, (float) collect($chartItems)->max('revenue'), (float) collect($chartItems)->max('reservations'));
        $revenuePoints = [];
        $reservationPoints = [];
        foreach ($chartItems as $index => $item) {
            $x = count($chartItems) > 1 ? $plotLeft + (($plotRight - $plotLeft) * $index / (count($chartItems) - 1)) : ($plotLeft + $plotRight) / 2;
            $revenuePoints[] = round($x, 2).','.round($plotBottom - ((float) $item['revenue'] / $maxValue * $plotHeight), 2);
            $reservationPoints[] = round($x, 2).','.round($plotBottom - ((float) $item['reservations'] / $maxValue * $plotHeight), 2);
        }
        $roomStops = [];
        $roomOffset = 0;
        $roomColors = ['available' => '--room-available', 'occupied' => '--room-occupied', 'reserved' => '--room-reserved', 'must_clean' => '--room-cleaning', 'maintenance' => '--room-maintenance', 'blocked' => '--room-blocked'];
        foreach (\App\Enums\RoomOperationalStatus::cases() as $status) {
            $count = (int) ($statusCounts[$status->value] ?? 0);
            if ($roomTotal > 0 && $count > 0) {
                $next = $roomOffset + ($count / $roomTotal * 100);
                $roomStops[] = 'var('.$roomColors[$status->value].') '.$roomOffset.'% '.$next.'%';
                $roomOffset = $next;
            }
        }
        $donutStyle = $roomStops ? 'background: conic-gradient('.implode(', ', $roomStops).');' : 'background: var(--surface-secondary);';
    @endphp

    <x-page-header title="Dashboard" subtitle="Live overview of today's hotel operations.">
        <a class="ui-button ui-button--primary" href="{{ route('reservations.create') }}"><x-ui.icon name="plus" size="17" /> New reservation</a>
    </x-page-header>

    <div class="metric-grid {{ !$financialVisible ? 'metric-grid--operational' : '' }}">
        @foreach ($metrics as $metric)
            @if(!empty($metric['href']))<a class="metric-card mini-kpi-card mini-kpi-card--link" href="{{ $metric['href'] }}" aria-label="{{ $metric['label'] }}: {{ $metric['value'] }}">@else<article class="metric-card">@endif
                <div class="metric-card__icon metric-card__icon--{{ $metric['tone'] }}"><x-ui.icon :name="$metric['icon']" size="20" /></div>
                <div><span>{{ $metric['label'] }}</span><strong>{{ $metric['value'] }}</strong><small>{{ $metric['hint'] }}</small></div>
            @if(!empty($metric['href']))</a>@else</article>@endif
        @endforeach
    </div>

    <div class="dashboard-grid dashboard-grid--main">
        <x-ui.card title="Revenue overview" icon="chart">
            <x-slot:header>
                <div class="dashboard-chart-header">
                    <nav class="segmented-control" aria-label="Revenue overview range">
                        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'custom' => 'Custom'] as $key => $label)
                            <a class="{{ $range === $key ? 'is-active' : '' }}" href="{{ route('dashboard', ['range' => $key]) }}" @if($range === $key) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                    @if ($range === 'custom')
                        <form class="dashboard-custom-range" method="GET" action="{{ route('dashboard') }}">
                            <input type="hidden" name="range" value="custom">
                            <input class="form-control" type="date" name="from" value="{{ $rangeFrom->toDateString() }}" aria-label="Chart from date">
                            <span>to</span>
                            <input class="form-control" type="date" name="to" value="{{ $rangeTo->toDateString() }}" aria-label="Chart to date">
                            <button class="ui-button ui-button--info" type="submit">Apply</button>
                        </form>
                    @endif
                </div>
            </x-slot:header>
            @if ($chart['hasData'])
                <div class="analytics-chart" role="img" aria-label="Revenue and reservation trend for the selected period">
                    <svg viewBox="0 0 780 260" preserveAspectRatio="none">
                        @foreach ([0, 25, 50, 75, 100] as $line)
                            <line x1="44" x2="748" y1="{{ $plotBottom - ($line / 100 * $plotHeight) }}" y2="{{ $plotBottom - ($line / 100 * $plotHeight) }}" class="chart-grid-line" />
                        @endforeach
                        <polyline points="{{ implode(' ', $revenuePoints) }} 748,218 44,218" class="chart-area" />
                        <polyline points="{{ implode(' ', $revenuePoints) }}" class="chart-series chart-series--revenue" />
                        <polyline points="{{ implode(' ', $reservationPoints) }}" class="chart-series chart-series--reservations" />
                        @foreach ($chartItems as $index => $item)
                            @php($x = count($chartItems) > 1 ? $plotLeft + (($plotRight - $plotLeft) * $index / (count($chartItems) - 1)) : ($plotLeft + $plotRight) / 2)
                            @php($revenueY = $plotBottom - ((float) $item['revenue'] / $maxValue * $plotHeight))
                            @php($reservationY = $plotBottom - ((float) $item['reservations'] / $maxValue * $plotHeight))
                            <circle cx="{{ $x }}" cy="{{ $revenueY }}" r="4" class="chart-point chart-point--revenue"><title>{{ $item['label'] }} · Revenue {{ app(\App\Support\CurrencyFormatter::class)->format($item['revenue']) }}</title></circle>
                            <circle cx="{{ $x }}" cy="{{ $reservationY }}" r="3" class="chart-point chart-point--reservations"><title>{{ $item['label'] }} · Reservations {{ $item['reservations'] }}</title></circle>
                        @endforeach
                        @foreach ($chartItems as $index => $item)
                            @php($x = count($chartItems) > 1 ? $plotLeft + (($plotRight - $plotLeft) * $index / (count($chartItems) - 1)) : ($plotLeft + $plotRight) / 2)
                            <text x="{{ $x }}" y="246" text-anchor="middle" class="chart-axis-label">{{ $item['label'] }}</text>
                        @endforeach
                    </svg>
                </div>
            @else
                <div class="analytics-empty"><x-ui.icon name="chart" size="28" /><strong>No revenue activity for this period.</strong><span>Reservation activity will appear here as bookings and payments are recorded.</span></div>
            @endif
            <div class="chart-legend" aria-label="Chart legend"><span class="legend-dot legend-dot--blue"></span> Revenue <span class="legend-dot legend-dot--orange"></span> Reservations</div>
        </x-ui.card>

        <x-ui.card title="Room status" icon="bed">
            <div class="room-status-placeholder">
                <div class="donut" style="{{ $donutStyle }}" role="img" aria-label="{{ $roomTotal }} active rooms by operational status"><strong>{{ $roomTotal }}</strong><span>Rooms</span></div>
                <div class="status-list">
                    @foreach (\App\Enums\RoomOperationalStatus::cases() as $status)
                        <span><i class="status-dot status-dot--{{ $status->value }}"></i>{{ $status->label() }} <b>{{ $statusCounts[$status->value] ?? 0 }}</b></span>
                    @endforeach
                </div>
            </div>
        </x-ui.card>
    </div>

    <div class="dashboard-grid dashboard-grid--secondary">
        <x-ui.card title="Recent reservations" icon="calendar">
            <x-slot:header><a href="{{ route('reservations.index') }}" class="text-link">View all</a></x-slot:header>
            <x-data.table caption="Recent reservations"><thead><tr><th>Code</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Total</th><th>Status</th></tr></thead><tbody>
                @forelse($recentReservations as $reservation)
                    <tr><td><a class="text-link" href="{{ route('reservations.show', $reservation) }}">{{ $reservation->code }}</a></td><td>{{ $reservation->client?->full_name }}</td><td><strong>{{ $reservation->room?->room_number }}</strong></td><td>{{ $reservation->check_in?->format('m/d/Y') }}</td><td>{{ $reservation->check_out?->format('m/d/Y') }}</td><td>{{ app(\App\Support\CurrencyFormatter::class)->format($reservation->total_amount) }}</td><td><x-ui.badge :variant="$reservation->status->badgeVariant()">{{ $reservation->status->label() }}</x-ui.badge></td></tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state">No reservations yet.</div></td></tr>
                @endforelse
            </tbody></x-data.table>
        </x-ui.card>

        <x-ui.card title="Upcoming tasks" icon="bell">
            <x-slot:header><a href="{{ route('tasks.index') }}" class="text-link dashboard-widget-link" aria-label="View all tasks">View tasks <x-ui.icon name="arrow-right" size="14" /></a></x-slot:header>
            <div class="task-list">
                @forelse($upcomingTasks as $task)
                    @php($taskVariant = $task['overdue'] ? 'danger' : (in_array($task['priority'], ['High', 'Urgent'], true) ? ($task['priority'] === 'Urgent' ? 'danger' : 'info') : 'neutral'))
                    <a class="task-row {{ $task['overdue'] ? 'task-row--overdue' : '' }}" href="{{ $task['href'] }}"><x-ui.icon :name="$task['icon']" size="18" /><span><strong>{{ $task['title'] }}</strong><small>{{ $task['detail'] }} @if($task['overdue']) · Overdue @endif</small></span><x-ui.badge :variant="$taskVariant">{{ $task['priority'] }}</x-ui.badge></a>
                @empty
                    <div class="empty-state"><strong>No upcoming tasks.</strong><span>Operations are clear.</span><a class="ui-button ui-button--secondary ui-button--small" href="{{ route('tasks.index') }}">Open tasks module</a></div>
                @endforelse
            </div>
        </x-ui.card>
    </div>
@endsection
