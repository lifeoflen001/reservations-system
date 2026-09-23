<?php

namespace App\Http\Controllers\Announcements;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementPushSubscription;
use App\Models\Department;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\PropertySettingsService;
use App\Support\TablePagination;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Announcement::class);
        $this->announcements->processDue();
        $query = Announcement::query()->with(['departments', 'attachments'])->latest('start_at')->latest('id');
        $user = $request->user();
        if (! $user->hasPermission('announcements.manage') && ! $user->hasPermission('announcements.statistics')) {
            $query->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id));
        }
        $search = trim((string) $request->input('search', ''));
        $query->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
            $like = '%'.$search.'%';
            $inner->where('title', 'like', $like)->orWhere('short_description', 'like', $like)->orWhere('category', 'like', $like);
        }));
        $query->when($request->filled('category') && $request->category !== 'all', fn ($q) => $q->where('category', $request->category));
        $query->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status));
        $query->when($request->filled('department_id') && $request->department_id !== 'all', fn ($q) => $q->whereHas('departments', fn ($d) => $d->whereKey($request->integer('department_id'))));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('start_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('start_at', '<=', $request->date('date_to')));
        $announcements = $query->paginate(TablePagination::perPage($request, 10))->withQueryString();
        $editAnnouncement = $request->filled('edit') ? Announcement::with(['departments', 'attachments'])->findOrFail($request->integer('edit')) : null;

        return view('announcements.index', [
            'announcements' => $announcements,
            'departments' => Department::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => AnnouncementService::CATEGORIES,
            'openNew' => $request->boolean('new'),
            'editAnnouncement' => $editAnnouncement,
        ]);
    }

    public function dashboard(Request $request): View
    {
        Gate::authorize('viewAny', Announcement::class);
        $tab = $request->input('tab', 'all');
        $query = Announcement::query()->with('departments')->visible()->latest('start_at');
        if (! $request->user()->hasPermission('announcements.manage')) $query->whereHas('recipients', fn ($q) => $q->where('user_id', $request->user()->id));
        match ($tab) {
            'priority' => $query->where('is_high_priority', true),
            'featured' => $query->where('is_featured', true),
            'upcoming' => $query->where('start_at', '>', now()),
            default => null,
        };
        return view('announcements.dashboard', ['announcements' => $query->paginate(TablePagination::perPage($request, 10))->withQueryString(), 'tab' => $tab]);
    }

    public function create(): RedirectResponse { Gate::authorize('create', Announcement::class); return redirect()->route('announcements.index', ['new' => 1]); }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Announcement::class);
        $data = $this->validated($request);
        $announcement = $this->announcements->save($data, $request->user());
        if ($request->boolean('publish_now')) {
            Gate::authorize('publish', $announcement);
            $this->announcements->publish($announcement, $request->user());
        }
        return redirect()->route('announcements.show', $announcement)->with('success', $request->boolean('publish_now') ? 'Announcement published.' : 'Announcement saved as a draft.');
    }

    public function show(Request $request, Announcement $announcement): View
    {
        Gate::authorize('view', $announcement);
        $announcement->load(['creator', 'publisher', 'departments', 'attachments', 'recipients.user']);
        $this->announcements->markViewed($announcement, $request->user());
        return view('announcements.show', compact('announcement'));
    }

    public function edit(Announcement $announcement): RedirectResponse { Gate::authorize('update', $announcement); return redirect()->route('announcements.index', ['edit' => $announcement->id]); }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('update', $announcement);
        $announcement = $this->announcements->save($this->validated($request, $announcement), $request->user(), $announcement);
        if (in_array($announcement->status, ['active', 'scheduled'], true)) {
            $this->announcements->snapshotAndNotify($announcement, $request->user());
        }
        if ($request->boolean('publish_now') && $announcement->status === 'draft') {
            Gate::authorize('publish', $announcement);
            $this->announcements->publish($announcement, $request->user());
        }
        return redirect()->route('announcements.show', $announcement)->with('success', 'Announcement updated.');
    }

    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('publish', $announcement);
        $this->announcements->publish($announcement, $request->user());
        return back()->with('success', 'Announcement published and notifications queued.');
    }

    public function archive(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('archive', $announcement);
        $announcement->update(['status' => 'archived', 'archived_at' => now()]);
        return redirect()->route('announcements.index')->with('success', 'Announcement archived.');
    }

    public function read(Request $request, Announcement $announcement): Response
    {
        Gate::authorize('view', $announcement);
        $this->announcements->markViewed($announcement, $request->user());
        return response()->noContent();
    }

    public function subscribe(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('announcements.view'), 403);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:700'],
            'public_key' => ['nullable', 'string', 'max:5000'],
            'auth_token' => ['nullable', 'string', 'max:5000'],
            'content_encoding' => ['nullable', 'string', 'max:50'],
        ]);
        AnnouncementPushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            ['public_key' => $data['public_key'] ?? null, 'auth_token' => $data['auth_token'] ?? null, 'content_encoding' => $data['content_encoding'] ?? null, 'last_used_at' => now()]
        );
        return response()->noContent();
    }

    public function unsubscribe(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('announcements.view'), 403);
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:700']]);
        AnnouncementPushSubscription::query()->where('user_id', $request->user()->id)->where('endpoint', $data['endpoint'])->delete();
        return response()->noContent();
    }

    public function statistics(Request $request, Announcement $announcement): View
    {
        Gate::authorize('statistics', $announcement);
        $announcement->load(['creator', 'departments']);
        $recipientQuery = $announcement->recipients();
        $total = (clone $recipientQuery)->count();
        $views = (clone $recipientQuery)->whereNotNull('first_viewed_at')->count();
        $emails = (clone $recipientQuery)->where('email_status', 'sent')->count();
        return view('announcements.statistics', compact('announcement', 'total', 'views', 'emails'));
    }

    public function download(Request $request, Announcement $announcement, AnnouncementAttachment $attachment)
    {
        Gate::authorize('view', $announcement);
        abort_unless($attachment->announcement_id === $announcement->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->stored_name), 404);
        return Storage::disk($attachment->disk)->download($attachment->stored_name, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    private function validated(Request $request, ?Announcement $announcement = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(AnnouncementService::CATEGORIES)],
            'short_description' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:50000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_featured' => ['nullable', 'boolean'],
            'is_high_priority' => ['nullable', 'boolean'],
            'is_company_wide' => ['nullable', 'boolean'],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp'],
            'publish_now' => ['nullable', 'boolean'],
        ]);
        $data['start_at'] = Carbon::parse($data['start_at'], app(PropertySettingsService::class)->timezone());
        $data['end_at'] = filled($data['end_at'] ?? null) ? Carbon::parse($data['end_at'], app(PropertySettingsService::class)->timezone()) : null;
        $data['attachments'] = $request->file('attachments', []);
        if (! ($data['is_company_wide'] ?? false) && empty($data['department_ids'] ?? [])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['department_ids' => 'Select at least one department or choose a company-wide announcement.']);
        }
        return $data;
    }
}
