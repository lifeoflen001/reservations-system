<?php

namespace App\Http\Controllers;

use App\Services\HotelAnalyticsService;
use App\Support\CurrencyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReportsController extends Controller
{
    public function __construct(private readonly HotelAnalyticsService $analytics) {}

    public function index(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->validatedRange($request);
        $report = $this->analytics->report($from, $to, $request->user()->hasPermission('payments.view'));

        return view('reports.index', compact('from', 'to', 'formatter') + $report);
    }

    public function export(Request $request): Response
    {
        Gate::authorize('reports.export');
        abort_unless($request->user()->hasPermission('payments.view'), 403);
        [$from, $to] = $this->validatedRange($request);
        $query = $this->analytics->reportReservationsQuery($from, $to)->reorder('reservations.id');
        $filename = 'hotel-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($query, $from, $to) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reservation code', 'Guest', 'Room', 'Room type', 'Source', 'Check-in', 'Check-out', 'Nights', 'Total amount', 'Paid', 'Balance', 'Status']);
            foreach ($query->lazyById(250, 'reservations.id') as $reservation) {
                $paid = (float) ($reservation->paid_amount ?? 0);
                $checkIn = $reservation->check_in->copy()->startOfDay()->max($from->copy()->startOfDay());
                $checkOut = $reservation->check_out->copy()->startOfDay()->min($to->copy()->startOfDay()->addDay());
                $nights = max(0, $checkIn->diffInDays($checkOut));
                fputcsv($handle, [$reservation->code, $reservation->client?->full_name, $reservation->room?->room_number, $reservation->room?->roomType?->name, $reservation->source?->name ?? 'Direct', $reservation->check_in?->toDateTimeString(), $reservation->check_out?->toDateTimeString(), $nights, $reservation->total_amount, $paid, max(0, (float) $reservation->total_amount - $paid), $reservation->status->label()]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function validatedRange(Request $request): array
    {
        $values = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        [$from, $to] = $this->analytics->reportRange($values['from'] ?? null, $values['to'] ?? null);
        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'Report periods cannot exceed 366 days.']);
        }
        return [$from, $to];
    }
}
