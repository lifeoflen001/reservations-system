<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\Maintenance\StoreMaintenanceTaskRequest;
use App\Models\MaintenanceTask;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use App\Services\MaintenanceTaskService;
use App\Services\MiniDashboardMetricsService;
use App\Services\PropertySettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceTaskService $tasks) {}
    public function index(Request $request, MiniDashboardMetricsService $metricsService)
    {
        Gate::authorize('viewAny', MaintenanceTask::class);
        $tasks = MaintenanceTask::with(['room', 'assignee'])->when($request->filled('status') && (string) $request->string('status') !== 'all', fn ($q) => $q->where('status', (string) $request->string('status')))->when($request->boolean('overdue'), function ($q) {
            $q->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])->whereNotNull('due_at')->where('due_at', '<', now(app(PropertySettingsService::class)->timezone()));
        })->orderByRaw('due_at is null')->orderBy('due_at')->latest()->orderByDesc('maintenance_tasks.id')->paginate(25)->withQueryString();
        $maintenanceDepartmentId = Department::where('name', 'Maintenance')->value('id');
        return view('maintenance.index', ['tasks' => $tasks, 'rooms' => Room::active()->with(['floor', 'roomType'])->orderBy('room_number')->get(), 'staff' => User::with(['department', 'role'])->where('is_active', true)->orderByRaw('department_id = ? desc', [$maintenanceDepartmentId])->orderBy('name')->get(), 'statuses' => TaskStatus::cases(), 'openNew' => $request->boolean('new'), 'editTask' => $request->filled('edit') ? MaintenanceTask::find($request->integer('edit')) : null, 'kpis' => $metricsService->maintenance()]);
    }
    public function create() { Gate::authorize('create', MaintenanceTask::class); return redirect()->route('maintenance.index', ['new' => 1]); }
    public function store(StoreMaintenanceTaskRequest $request) { Gate::authorize('create', MaintenanceTask::class); try { $this->tasks->create($request->validated(), $request->user()->id); } catch (LogicException $e) { return back()->withInput()->with('error', $e->getMessage()); } return redirect()->route('maintenance.index')->with('success', 'Maintenance task created.'); }
    public function edit(MaintenanceTask $maintenance) { Gate::authorize('update', $maintenance); return redirect()->route('maintenance.index', ['edit' => $maintenance->id]); }
    public function update(StoreMaintenanceTaskRequest $request, MaintenanceTask $maintenance) { Gate::authorize('update', $maintenance); try { $this->tasks->update($maintenance, $request->validated(), $request->user()->id); } catch (LogicException $e) { return back()->withInput()->with('error', $e->getMessage()); } return redirect()->route('maintenance.index')->with('success', 'Maintenance task updated.'); }
    public function complete(Request $request, MaintenanceTask $maintenance) { Gate::authorize('complete', $maintenance); try { $this->tasks->complete($maintenance, $request->user()->id); } catch (LogicException $e) { return back()->with('error', $e->getMessage()); } return back()->with('success', 'Maintenance task completed.'); }
    public function destroy(Request $request, MaintenanceTask $maintenance) { Gate::authorize('delete', $maintenance); try { $this->tasks->cancel($maintenance, $request->user()->id); } catch (LogicException $e) { return back()->with('error', $e->getMessage()); } return back()->with('success', 'Maintenance task cancelled.'); }
}
