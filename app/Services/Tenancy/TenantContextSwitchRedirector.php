<?php

namespace App\Services\Tenancy;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantContextSwitchRedirector
{
    public function response(Request $request): RedirectResponse
    {
        $returnTo = (string) $request->input('return_to', '');
        $path = parse_url($returnTo, PHP_URL_PATH);
        $query = parse_url($returnTo, PHP_URL_QUERY);

        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return redirect()->route('dashboard');
        }

        $safePath = match (true) {
            $path === '/dashboard' => route('dashboard'),
            $path === '/reservations' => route('reservations.index'),
            $path === '/rooms' => route('rooms.index'),
            $path === '/room-planning' => route('room-planning.index'),
            $path === '/tasks' => route('tasks.index'),
            $path === '/pos/terminal' => route('pos.terminal'),
            $path === '/finance/overview' => route('finance.overview'),
            $path === '/reports' => route('reports.index'),
            str_starts_with($path, '/reservations/') => route('reservations.index'),
            str_starts_with($path, '/rooms/') => route('rooms.index'),
            str_starts_with($path, '/tasks/') => route('tasks.index'),
            str_starts_with($path, '/pos/orders/') || str_starts_with($path, '/pos/receipts/') => route('pos.orders'),
            str_starts_with($path, '/finance/') => route('finance.overview'),
            str_starts_with($path, '/invoices/') => route('finance.overview'),
            default => route('dashboard'),
        };

        return redirect()->to($safePath.($query && in_array($path, ['/reservations', '/rooms', '/room-planning', '/tasks', '/pos/terminal', '/finance/overview', '/reports'], true) ? '?'.$query : ''));
    }
}
