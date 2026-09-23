<?php

namespace App\Http\Controllers\Rooms;

use App\Enums\HousekeepingStatus;
use App\Enums\RoomOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\StoreRoomRequest;
use App\Models\Amenity;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomStatus;
use App\Models\RoomType;
use App\Services\RoomService;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use LogicException;

class RoomController extends Controller
{
    public function __construct(private readonly RoomService $rooms) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Room::class);
        $rooms = Room::query()->with(['floor', 'category', 'roomType.amenities'])
            ->when($request->filled('search'), fn ($q) => $q->where('room_number', 'like', '%'.(string) $request->string('search').'%'))
            ->when($request->filled('status') && (string) $request->string('status') !== 'all', fn ($q) => $q->where('operational_status', (string) $request->string('status')))
            ->when($request->filled('housekeeping') && (string) $request->string('housekeeping') !== 'all', fn ($q) => $q->where('housekeeping_status', (string) $request->string('housekeeping')))
            ->when($request->filled('floor_id') && (string) $request->string('floor_id') !== 'all', fn ($q) => $q->where('floor_id', $request->integer('floor_id')))
            ->orderBy('room_number')->paginate(TablePagination::perPage($request, 15))->withQueryString();

        $openRoom = $request->filled('room') ? Room::with(['floor', 'category', 'roomType.amenities', 'reservations.client', 'maintenanceTasks', 'housekeepingTasks'])->find($request->integer('room')) : null;
        $editRoom = $request->filled('edit') ? Room::find($request->integer('edit')) : null;
        $editFloor = $request->filled('edit_floor') ? Floor::find($request->integer('edit_floor')) : null;
        $editCategory = $request->filled('edit_category') ? RoomCategory::find($request->integer('edit_category')) : null;
        $editType = $request->filled('edit_type') ? RoomType::find($request->integer('edit_type')) : null;
        $editStatus = $request->filled('edit_status') ? RoomStatus::find($request->integer('edit_status')) : null;
        return view('rooms.index', [
            'rooms' => $rooms,
            'mapRooms' => Room::active()->with(['floor', 'category', 'roomType'])->orderBy('room_number')->get(),
            'floors' => Floor::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'allFloors' => Floor::withCount('rooms')->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => RoomCategory::withCount('rooms')->orderBy('sort_order')->orderBy('name')->get(),
            'types' => RoomType::with('amenities')->withCount('rooms')->orderBy('sort_order')->orderBy('name')->get(),
            'amenities' => Amenity::orderBy('name')->get(),
            'statuses' => RoomOperationalStatus::cases(),
            'statusDefinitions' => RoomStatus::query()->orderBy('sort_order')->orderBy('name')->get(),
            'housekeepingStatuses' => HousekeepingStatus::cases(),
            'openNew' => $request->boolean('new'),
            'openRoom' => $openRoom,
            'editRoom' => $editRoom,
            'editFloor' => $editFloor,
            'editCategory' => $editCategory,
            'editType' => $editType,
            'editStatus' => $editStatus,
            'openStatusForm' => $request->boolean('new_status') || $editStatus !== null,
        ]);
    }

    public function create() { Gate::authorize('create', Room::class); return redirect()->route('rooms.index', ['new' => 1]); }
    public function show(Room $room) { Gate::authorize('view', $room); return redirect()->route('rooms.index', ['room' => $room->id]); }
    public function edit(Room $room) { Gate::authorize('update', $room); return redirect()->route('rooms.index', ['edit' => $room->id]); }

    public function store(StoreRoomRequest $request)
    {
        Gate::authorize('create', Room::class);
        $room = $this->rooms->create($request->validated(), $request->user()->id);
        return redirect()->route('rooms.index', ['room' => $room->id])->with('success', 'Room created successfully.');
    }

    public function update(StoreRoomRequest $request, Room $room)
    {
        Gate::authorize('update', $room);
        try { $room = $this->rooms->update($room, $request->validated(), $request->user()->id); }
        catch (LogicException $exception) { return back()->withInput()->with('error', $exception->getMessage()); }
        return redirect()->route('rooms.index', ['room' => $room->id])->with('success', 'Room updated successfully.');
    }

    public function destroy(Request $request, Room $room)
    {
        Gate::authorize('delete', $room);
        $this->rooms->archive($room, $request->user()->id);
        return redirect()->route('rooms.index', ['tab' => 'list'])->with('success', 'Room archived.');
    }

    public function status(Request $request, Room $room)
    {
        Gate::authorize('manageStatus', $room);
        $data = $request->validate(['operational_status' => ['required', Rule::enum(RoomOperationalStatus::class)]]);
        try { $this->rooms->setStatus($room, RoomOperationalStatus::from($data['operational_status']), $request->user()->id); }
        catch (LogicException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Room status updated.');
    }

    public function categoryStore(Request $request)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:room_categories,name'], 'description' => ['nullable', 'string', 'max:1000'], 'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        RoomCategory::create($data);
        return back()->with('success', 'Room category created.');
    }

    public function categoryUpdate(Request $request, RoomCategory $category)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('room_categories', 'name')->ignore($category)], 'description' => ['nullable', 'string', 'max:1000'], 'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'], 'is_active' => ['sometimes', 'boolean']]);
        $category->update($data);
        return back()->with('success', 'Room category updated.');
    }

    public function categoryDestroy(Request $request, RoomCategory $category)
    {
        $this->authorizeCatalog($request);
        if ($category->rooms()->exists()) return back()->with('error', 'Categories assigned to rooms cannot be deleted.');
        $category->delete(); return back()->with('success', 'Room category deleted.');
    }

    public function typeStore(Request $request)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:room_types,name'], 'description' => ['nullable', 'string', 'max:1000'], 'capacity' => ['required', 'integer', 'min:1', 'max:255'], 'bed_type' => ['nullable', 'string', 'max:100'], 'base_rate' => ['required', 'numeric', 'min:0']]);
        RoomType::create($data); return back()->with('success', 'Room type created.');
    }

    public function typeUpdate(Request $request, RoomType $type)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('room_types', 'name')->ignore($type)], 'description' => ['nullable', 'string', 'max:1000'], 'capacity' => ['required', 'integer', 'min:1', 'max:255'], 'bed_type' => ['nullable', 'string', 'max:100'], 'base_rate' => ['required', 'numeric', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        $type->update($data); return back()->with('success', 'Room type updated.');
    }

    public function typeDestroy(Request $request, RoomType $type)
    {
        $this->authorizeCatalog($request);
        if ($type->rooms()->exists()) return back()->with('error', 'Room types assigned to rooms cannot be deleted.');
        $type->delete(); return back()->with('success', 'Room type deleted.');
    }

    public function floorStore(Request $request)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:floors,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        Floor::create([...$data, 'is_active' => true]);

        return back()->with('success', 'Floor created.');
    }

    public function floorUpdate(Request $request, Floor $floor)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('floors', 'name')->ignore($floor)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_active', $data) && filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) === false && $floor->rooms()->exists()) {
            return back()->withInput()->with('error', 'A floor with rooms assigned to it cannot be deactivated.');
        }

        $floor->update($data);

        return back()->with('success', 'Floor updated.');
    }

    public function floorDestroy(Request $request, Floor $floor)
    {
        $this->authorizeCatalog($request);
        if ($floor->rooms()->exists()) {
            return back()->with('error', 'Floors assigned to rooms cannot be deleted.');
        }

        $floor->delete();

        return back()->with('success', 'Floor deleted.');
    }

    public function statusStore(Request $request)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate($this->statusRules());

        if (RoomStatus::where('code', $data['code'])->exists()) {
            return back()->withInput()->with('error', 'That room status is already configured.');
        }

        RoomStatus::create([
            ...$data,
            'is_sellable' => $request->boolean('is_sellable'),
            'is_system' => true,
        ]);

        return redirect()->route('rooms.index', ['tab' => 'statuses'])->with('success', 'Room status created.');
    }

    public function statusUpdate(Request $request, RoomStatus $status)
    {
        $this->authorizeCatalog($request);
        $data = $request->validate($this->statusRules($status));

        if ($status->is_system) {
            unset($data['code']);
        }

        $status->update([
            ...$data,
            'is_sellable' => $request->boolean('is_sellable'),
        ]);

        return redirect()->route('rooms.index', ['tab' => 'statuses'])->with('success', 'Room status updated.');
    }

    public function statusDestroy(Request $request, RoomStatus $status)
    {
        $this->authorizeCatalog($request);

        if ($status->is_system || Room::where('operational_status', $status->code)->exists()) {
            return back()->with('error', 'System room statuses are required by room operations and cannot be deleted.');
        }

        $status->delete();

        return back()->with('success', 'Room status deleted.');
    }

    private function statusRules(?RoomStatus $status = null): array
    {
        $codes = array_map(static fn (RoomOperationalStatus $case) => $case->value, RoomOperationalStatus::cases());

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::in($codes), Rule::unique('room_statuses', 'code')->ignore($status)],
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_sellable' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    private function authorizeCatalog(Request $request): void
    {
        abort_unless($request->user()->roleName() === 'super_administrator' || $request->user()->hasPermission('room_categories.manage') || $request->user()->hasPermission('room_types.manage') || $request->user()->hasPermission('floors.manage') || $request->user()->hasPermission('rooms.manage') || $request->user()->hasPermission('rooms.manage_status'), 403);
    }
}
