<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformSupportSession;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformSupportContext;
use App\Services\Platform\PlatformSupportQueryService;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformSupportWorkspaceController extends Controller
{
    public function dashboard(PlatformSupportSession $platformSupportSession, PlatformSupportContext $context, PlatformSupportQueryService $queries): View
    {
        return $this->view('dashboard', ['data' => $queries->dashboard()]);
    }

    public function module(PlatformSupportSession $platformSupportSession, string $module, Request $request, PlatformSupportQueryService $queries, EntitlementService $entitlements): View
    {
        $feature = ['pos' => 'pos', 'finance' => 'finance', 'reports' => 'reports', 'room-planning' => 'room_planning'][$module] ?? null;
        if ($feature && ! $entitlements->hasFeature($platformSupportSession->organization, $feature)) {
            abort(403, $entitlements->explainFeatureDenial($platformSupportSession->organization, $feature));
        }

        $data = match ($module) {
            'reservations' => ['rows' => $queries->reservations($request->string('q')->toString()), 'search' => $request->string('q')->toString()],
            'clients' => ['rows' => $queries->clients($request->string('q')->toString()), 'search' => $request->string('q')->toString()],
            'rooms' => ['rows' => $queries->rooms()],
            'room-planning' => $queries->roomPlanning(),
            'tasks' => ['rows' => $queries->tasks()],
            'housekeeping' => ['rows' => $queries->housekeeping()],
            'maintenance' => ['rows' => $queries->maintenance()],
            'pos' => ['rows' => $queries->pos()],
            'finance' => $queries->finance(),
            'reports' => $queries->reports(),
            'settings' => $queries->settings(),
            default => abort(404),
        };

        return $this->view('module', ['data' => $data, 'module' => $module]);
    }

    public function detail(PlatformSupportSession $platformSupportSession, string $module, int $id, PlatformSupportQueryService $queries): View
    {
        $record = match ($module) {
            'reservations' => $queries->reservation($id),
            'clients' => $queries->client($id),
            default => abort(404),
        };

        return $this->view('detail', ['module' => $module, 'record' => $record]);
    }

    public function search(PlatformSupportSession $platformSupportSession, Request $request, PlatformSupportQueryService $queries): View
    {
        return $this->view('search', ['term' => $request->string('q')->toString(), 'results' => $queries->search($request->string('q')->toString())]);
    }

    private function view(string $view, array $data): View
    {
        $session = app(PlatformSupportContext::class)->current();
        return view('platform.support.workspace.'.$view, array_merge($data, [
            'session' => $session,
            'entitlements' => app(EntitlementService::class)->snapshot($session->organization),
        ]));
    }
}
