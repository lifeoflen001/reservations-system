<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\Housekeeping\StoreHousekeepingTaskRequest;
use App\Models\HousekeepingTask;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use App\Services\MiniDashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class HousekeepingController extends Controller
{
    public function __construct(private readonly HousekeepingService $tasks) {}
    public function index(Request $request, MiniDashboardMetricsService $metricsService)
    {
        Gate::authorize('viewAny', HousekeepingTask::class);
        $tasks = HousekeepingTask::with(['room', 'assignee'])->when($request->filled('status') && (string) $request->string('status') !== 'all', fn ($q) => $q->where('status', (string) $request->string('status')))->orderByRaw('due_at is null')->orderBy('due_at')->latest()->orderByDesc('housekeeping_tasks.id')->paginate(25)->withQueryString();
        $housekeepingDepartmentId = Department::where('name', 'Housekeeping')->value('id');
        return view('housekeeping.index', ['tasks' => $tasks, 'rooms' => Room::active()->with(['floor', 'roomType'])->orderBy('room_number')->get(), 'staff' => User::with(['department', 'role'])->where('is_active', true)->orderByRaw('department_id = ? desc', [$housekeepingDepartmentId])->orderBy('name')->get(), 'statuses' => TaskStatus::cases(), 'openNew' => $request->boolean('new'), 'editTask' => $request->filled('edit') ? HousekeepingTask::find($request->integer('edit')) : null, 'kpis' => $metricsService->housekeeping()]);
    }
    public function create() { Gate::authorize('create', HousekeepingTask::class); return redirect()->route('housekeeping.index', ['new' => 1]); }
    public function store(StoreHousekeepingTaskRequest $request) { Gate::authorize('create', HousekeepingTask::class); $this->tasks->create($request->validated(), $request->user()->id); return redirect()->route('housekeeping.index')->with('success', 'Housekeeping task created.'); }
    public function edit(HousekeepingTask $housekeeping) { Gate::authorize('update', $housekeeping); return redirect()->route('housekeeping.index', ['edit' => $housekeeping->id]); }
    public function update(StoreHousekeepingTaskRequest $request, HousekeepingTask $housekeeping) { Gate::authorize('update', $housekeeping); try { $this->tasks->update($housekeeping, $request->validated(), $request->user()->id); } catch (LogicException $e) { return back()->withInput()->with('error', $e->getMessage()); } return redirect()->route('housekeeping.index')->with('success', 'Housekeeping task updated.'); }
    public function complete(Request $request, HousekeepingTask $housekeeping) { Gate::authorize('complete', $housekeeping); try { $this->tasks->complete($housekeeping, $request->user()->id); } catch (LogicException $e) { return back()->with('error', $e->getMessage()); } return back()->with('success', 'Housekeeping task completed.'); }
    public function destroy(Request $request, HousekeepingTask $housekeeping) { Gate::authorize('delete', $housekeeping); try { $this->tasks->cancel($housekeeping, $request->user()->id); } catch (LogicException $e) { return back()->with('error', $e->getMessage()); } return back()->with('success', 'Housekeeping task cancelled.'); }
}
