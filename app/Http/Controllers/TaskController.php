<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\StoreCommentRequest;
use App\Http\Requests\Tasks\StoreSubtaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Requests\Tasks\UploadTaskAttachmentRequest;
use App\Models\Client;
use App\Models\Department;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskSubtask;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Task::class);
        $query = Task::query()->active()->with(['department', 'room', 'reservation.client', 'client', 'assignees.department']);
        $this->applyVisibility($query, $request);
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') $query->where(function ($q) use ($search): void {
            $like = '%'.$search.'%';
            $q->where('task_number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('description', 'like', $like)
                ->orWhereHas('room', fn ($room) => $room->where('room_number', 'like', $like))
                ->orWhereHas('reservation', fn ($reservation) => $reservation->where('code', 'like', $like))
                ->orWhereHas('client', fn ($client) => $client->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
                ->orWhereHas('assignees', fn ($assignee) => $assignee->where('name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
                ->orWhereHas('department', fn ($department) => $department->where('name', 'like', $like));
        });
        $query->when($request->filled('status') && $request->input('status') !== 'all', fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority') && $request->input('priority') !== 'all', fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('department_id') && $request->input('department_id') !== 'all', fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('assignee_id') && $request->input('assignee_id') !== 'all', fn ($q) => $q->whereHas('assignees', fn ($assignee) => $assignee->whereKey($request->integer('assignee_id'))))
            ->when($request->filled('room_id') && $request->input('room_id') !== 'all', fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('due_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('due_at', '<=', $request->date('date_to')));
        $sorts = ['task_number' => ['task_number', 'asc'], 'due_at' => ['due_at', 'asc'], 'status' => ['status', 'asc'], 'priority' => ['priority', 'desc'], 'created_at' => ['created_at', 'desc']];
        [$sortColumn, $sortDirection] = $sorts[(string) $request->input('sort', 'created_at')] ?? $sorts['created_at'];
        $tasks = $query->orderByRaw('due_at is null')->orderBy($sortColumn, $sortDirection)->paginate(20)->withQueryString();

        $metricsQuery = Task::query()->active();
        $this->applyVisibility($metricsQuery, $request);
        $metrics = ['total' => (clone $metricsQuery)->count(), 'pending' => (clone $metricsQuery)->whereIn('status', [TaskStatus::New->value, TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::OnHold->value])->count(), 'completed' => (clone $metricsQuery)->where('status', TaskStatus::Completed->value)->count(), 'overdue' => (clone $metricsQuery)->overdue()->count()];
        $editTask = $request->filled('edit') ? Task::with($this->tasks->detailRelations())->findOrFail($request->integer('edit')) : null;
        return view('tasks.index', ['tasks' => $tasks, 'metrics' => $metrics, 'departments' => Department::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(), 'rooms' => Room::active()->with('roomType')->orderBy('room_number')->get(), 'reservations' => Reservation::with(['client', 'room'])->whereNotIn('status', ['cancelled', 'no_show'])->latest('id')->limit(150)->get(), 'clients' => Client::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->limit(150)->get(), 'staff' => User::with(['department', 'role'])->where('is_active', true)->orderBy('name')->get(), 'statuses' => TaskStatus::cases(), 'priorities' => TaskPriority::cases(), 'categories' => ['General', 'Front Desk', 'Guest Request', 'Housekeeping', 'Maintenance', 'Reservation', 'Payment Follow-up', 'Transport', 'Management', 'Finance', 'Inspection'], 'openNew' => $request->boolean('new'), 'editTask' => $editTask]);
    }

    public function create(): RedirectResponse { Gate::authorize('create', Task::class); return redirect()->route('tasks.index', ['new' => 1]); }
    public function store(StoreTaskRequest $request): RedirectResponse { Gate::authorize('create', Task::class); $task = $this->tasks->create($this->normalizeInput($request->validated()), $request->user()->id); return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.'); }
    public function show(Task $task)
    {
        $task->load($this->tasks->detailRelations()); Gate::authorize('view', $task);
        return view('tasks.show', ['task' => $task, 'staff' => User::with(['department', 'role'])->where('is_active', true)->orderBy('name')->get(), 'statuses' => TaskStatus::cases(), 'priorities' => TaskPriority::cases()]);
    }
    public function edit(Task $task): RedirectResponse { Gate::authorize('update', $task); return redirect()->route('tasks.index', ['edit' => $task->id]); }
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse { Gate::authorize('update', $task); $this->tasks->update($task, $this->normalizeInput($request->validated()), $request->user()->id); return redirect()->route('tasks.show', $task)->with('success', 'Task updated successfully.'); }
    public function status(Request $request, Task $task): RedirectResponse { Gate::authorize('update', $task); $data = $request->validate(['status' => [Rule::enum(TaskStatus::class)]]); $this->tasks->update($task, $data, $request->user()->id); return back()->with('success', 'Task status updated.'); }
    public function complete(Request $request, Task $task): RedirectResponse { $task->load('assignees'); Gate::authorize('complete', $task); $this->tasks->complete($task, $request->user()->id); return back()->with('success', 'Task completed.'); }
    public function reopen(Request $request, Task $task): RedirectResponse { Gate::authorize('reopen', $task); $this->tasks->reopen($task, $request->user()->id); return back()->with('success', 'Task reopened.'); }
    public function archive(Request $request, Task $task): RedirectResponse { Gate::authorize('archive', $task); $this->tasks->archive($task, $request->user()->id); return redirect()->route('tasks.index')->with('success', 'Task archived.'); }
    public function assign(Request $request, Task $task): RedirectResponse { Gate::authorize('assign', $task); $data = $request->validate(['assignee_ids' => ['array'], 'assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)]]); $this->tasks->update($task, ['assignee_ids' => $data['assignee_ids'] ?? []], $request->user()->id); return back()->with('success', 'Task assignments updated.'); }
    public function comment(StoreCommentRequest $request, Task $task): RedirectResponse { Gate::authorize('comment', $task); $this->tasks->comment($task, $request->validated(), $request->user()->id); return back()->with('success', 'Comment posted.'); }
    public function subtask(StoreSubtaskRequest $request, Task $task): RedirectResponse { Gate::authorize('update', $task); $this->tasks->addSubtask($task, $request->string('title')->toString(), $request->user()->id); return back()->with('success', 'Subtask added.'); }
    public function toggleSubtask(Request $request, Task $task, TaskSubtask $subtask): RedirectResponse { abort_unless($subtask->task_id === $task->id, 404); Gate::authorize('update', $task); $this->tasks->toggleSubtask($subtask, $request->boolean('is_completed'), $request->user()->id); return back()->with('success', 'Subtask updated.'); }
    public function upload(UploadTaskAttachmentRequest $request, Task $task): RedirectResponse { Gate::authorize('uploadAttachment', $task); $this->tasks->storeAttachment($task, $request->file('attachment'), $request->user()->id); return back()->with('success', 'Attachment uploaded.'); }
    public function download(Task $task, TaskAttachment $attachment): StreamedResponse { abort_unless($attachment->task_id === $task->id, 404); Gate::authorize('view', $task); abort_unless(Storage::disk($attachment->disk)->exists($attachment->stored_name), 404); return Storage::disk($attachment->disk)->download($attachment->stored_name, $attachment->original_name); }
    public function deleteAttachment(Request $request, Task $task, TaskAttachment $attachment): RedirectResponse { abort_unless($attachment->task_id === $task->id, 404); Gate::authorize('uploadAttachment', $task); $this->tasks->deleteAttachment($attachment, $request->user()->id); return back()->with('success', 'Attachment removed.'); }
    public function startTimer(Request $request, Task $task): RedirectResponse { Gate::authorize('trackTime', $task); $this->tasks->startTimer($task, $request->user()->id); return back()->with('success', 'Timer started.'); }
    public function stopTimer(Request $request, Task $task): RedirectResponse { Gate::authorize('trackTime', $task); $this->tasks->stopTimer($task, $request->user()->id); return back()->with('success', 'Timer stopped.'); }

    private function applyVisibility($query, Request $request): void
    {
        $user = $request->user();
        if (! $user->hasPermission('tasks.view_all_departments') && ! $user->hasPermission('tasks.manage')) $query->where(function ($q) use ($user) { $q->where('created_by', $user->id)->orWhereHas('assignees', fn ($assignee) => $assignee->whereKey($user->id))->orWhereNull('department_id')->orWhere('department_id', $user->department_id); });
    }

    private function normalizeInput(array $data): array
    {
        if (array_key_exists('tags_text', $data)) $data['tags'] = collect(explode(',', (string) $data['tags_text']))->map(fn ($tag) => trim($tag))->filter()->values()->all();
        unset($data['tags_text']);
        return $data;
    }
}
