<?php

namespace App\Http\Controllers;

use App\Services\HotelAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HotelAnalyticsService $analytics): View
    {
        Gate::authorize('dashboard.view');
        $range = in_array($request->input('range', 'daily'), ['daily', 'weekly', 'monthly', 'yearly', 'custom'], true)
            ? $request->input('range', 'daily')
            : 'daily';
        $values = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = ! empty($values['from']) ? \Carbon\Carbon::createFromFormat('Y-m-d', $values['from'], config('app.timezone')) : null;
        $to = ! empty($values['to']) ? \Carbon\Carbon::createFromFormat('Y-m-d', $values['to'], config('app.timezone')) : null;
        if ($from && $to && $from->greaterThan($to)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        $data = $analytics->dashboard($range, $from, $to, Gate::allows('payments.view'));

        return view('dashboard', $data);
    }
}
