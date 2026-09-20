<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Floor;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTask;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomStatus;
use App\Models\RoomType;
use App\Models\User;
use App\Services\HousekeepingService;
use App\Services\MaintenanceTaskService;
use App\Services\RoomAvailabilityService;
use App\Services\RoomStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoomOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_map_and_list_are_database_backed(): void
    {
        $room = $this->room();
        $user = $this->user(config('hotel.permissions'));
        $this->actingAs($user)->get(route('rooms.index'))->assertOk()->assertSee('Floor 1')->assertSee($room->room_number);
        $this->actingAs($user)->get(route('rooms.index', ['tab' => 'list']))->assertOk()->assertSee('Room list')->assertSee('Standard Room');
    }

    public function test_status_catalog_matches_the_reference_table(): void
    {
        $user = $this->user(config('hotel.permissions'));

        $this->actingAs($user)->get(route('rooms.index', ['tab' => 'statuses']))
            ->assertOk()
            ->assertSee('Statuses')
            ->assertSee('Code')
            ->assertSee('Sellable')
            ->assertSee('available')
            ->assertSee('#42c55e')
            ->assertSee('must_clean')
            ->assertSee('#64748b')
            ->assertDontSee('Housekeeping conditions');
    }

    public function test_room_status_can_be_edited_and_the_new_form_can_restore_a_missing_system_status(): void
    {
        $user = $this->user(config('hotel.permissions'));
        $available = RoomStatus::where('code', 'available')->firstOrFail();

        $this->actingAs($user)->get(route('rooms.index', ['tab' => 'statuses', 'edit_status' => $available->id]))
            ->assertOk()->assertSee('Edit room status')->assertSee('Available');

        $this->actingAs($user)->put(route('rooms.statuses.update', $available), [
            'code' => 'available', 'name' => 'Ready', 'color' => '#123456', 'is_sellable' => '1', 'sort_order' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('room_statuses', ['id' => $available->id, 'name' => 'Ready', 'color' => '#123456', 'is_sellable' => 1, 'sort_order' => 5]);

        RoomStatus::where('code', 'blocked')->delete();
        $this->actingAs($user)->get(route('rooms.index', ['tab' => 'statuses', 'new_status' => 1]))
            ->assertOk()->assertSee('New room status');
        $this->actingAs($user)->post(route('rooms.statuses.store'), [
            'code' => 'blocked', 'name' => 'Blocked', 'color' => '#64748b', 'sort_order' => 60,
        ])->assertRedirect();
        $this->assertDatabaseHas('room_statuses', ['code' => 'blocked', 'is_system' => 1]);
    }

    public function test_system_room_status_cannot_be_deleted(): void
    {
        $user = $this->user(config('hotel.permissions'));
        $status = RoomStatus::where('code', 'maintenance')->firstOrFail();

        $this->actingAs($user)->delete(route('rooms.statuses.destroy', $status))
            ->assertRedirect()->assertSessionHas('error', 'System room statuses are required by room operations and cannot be deleted.');
        $this->assertDatabaseHas('room_statuses', ['id' => $status->id]);
    }

    public function test_floor_catalog_can_create_and_cannot_remove_a_floor_assigned_to_a_room(): void
    {
        $user = $this->user(config('hotel.permissions'));

        $this->actingAs($user)->get(route('rooms.index', ['tab' => 'catalog']))
            ->assertOk()->assertSee('Floors')->assertSee('No floors yet.');

        $this->actingAs($user)->post(route('rooms.floors.store'), ['name' => 'Floor 1', 'sort_order' => 1])
            ->assertRedirect();
        $floor = Floor::where('name', 'Floor 1')->firstOrFail();
        $this->assertTrue($floor->is_active);

        $this->actingAs($user)->post(route('rooms.floors.store'), ['name' => 'Floor 2', 'sort_order' => 2]);
        $floorTwo = Floor::where('name', 'Floor 2')->firstOrFail();
        $this->actingAs($user)->put(route('rooms.floors.update', $floorTwo), ['name' => 'Upper Floor', 'sort_order' => 2, 'is_active' => '1'])
            ->assertRedirect();
        $this->assertSame('Upper Floor', $floorTwo->fresh()->name);

        $this->roomOnFloor($floor);
        $this->actingAs($user)->delete(route('rooms.floors.destroy', $floor))
            ->assertRedirect()->assertSessionHas('error', 'Floors assigned to rooms cannot be deleted.');
        $this->assertDatabaseHas('floors', ['id' => $floor->id]);
    }

    public function test_active_rooms_are_available_to_housekeeping_and_maintenance(): void
    {
        $room = $this->room();
        $user = $this->user(config('hotel.permissions'));

        $this->actingAs($user)->get(route('housekeeping.index', ['new' => 1]))
            ->assertOk()->assertSee($room->room_number);
        $this->actingAs($user)->get(route('maintenance.index', ['new' => 1]))
            ->assertOk()->assertSee($room->room_number);
    }

    public function test_room_can_be_created_without_a_floor(): void
    {
        $category = RoomCategory::create(['name' => 'Standard']);
        $type = RoomType::create(['name' => 'Standard Room', 'capacity' => 2, 'base_rate' => 95]);
        $user = $this->user(config('hotel.permissions'));

        $this->actingAs($user)->post(route('rooms.store'), [
            'room_number' => '101',
            'room_category_id' => $category->id,
            'room_type_id' => $type->id,
            'operational_status' => RoomOperationalStatus::Available->value,
            'housekeeping_status' => HousekeepingStatus::Clean->value,
            'base_rate' => 95,
            'capacity' => 2,
            'is_active' => '1',
        ])->assertRedirect();

        $room = Room::where('room_number', '101')->firstOrFail();
        $this->assertNull($room->floor_id);
        $this->actingAs($user)->get(route('rooms.index'))->assertOk()->assertSee('Unassigned floor')->assertSee('101');
    }

    public function test_maintenance_changes_room_state_and_completion_recalculates_it(): void
    {
        $room = $this->room();
        $task = app(MaintenanceTaskService::class)->create(['room_id' => $room->id, 'issue' => 'Broken lock', 'priority' => TaskPriority::High->value, 'cost' => 25, 'status' => TaskStatus::Pending->value]);
        $this->assertSame(RoomOperationalStatus::Maintenance, $room->fresh()->operational_status);
        $this->assertFalse(app(RoomAvailabilityService::class)->isAvailable($room, now()->addDay(), now()->addDays(2)));
        app(MaintenanceTaskService::class)->complete($task);
        $this->assertSame(RoomOperationalStatus::Available, $room->fresh()->operational_status);
    }

    public function test_moving_maintenance_reconciles_the_previous_and_new_rooms(): void
    {
        $floor = Floor::create(['name' => 'Floor 2']);
        $category = RoomCategory::create(['name' => 'Suite']);
        $type = RoomType::create(['name' => 'Suite Room', 'capacity' => 2, 'base_rate' => 125]);
        $oldRoom = Room::create(['room_number' => '201', 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 125, 'capacity' => 2]);
        $newRoom = Room::create(['room_number' => '202', 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 125, 'capacity' => 2]);

        $task = app(MaintenanceTaskService::class)->create(['room_id' => $oldRoom->id, 'issue' => 'Broken lock', 'priority' => TaskPriority::High->value, 'cost' => 25, 'status' => TaskStatus::Pending->value]);
        app(MaintenanceTaskService::class)->update($task, ['room_id' => $newRoom->id, 'issue' => 'Broken lock', 'priority' => TaskPriority::High->value, 'cost' => 25, 'status' => TaskStatus::Pending->value]);

        $this->assertSame($newRoom->id, $task->fresh()->room_id);
        $this->assertSame(RoomOperationalStatus::Available, $oldRoom->fresh()->operational_status);
        $this->assertSame(RoomOperationalStatus::Maintenance, $newRoom->fresh()->operational_status);
    }

    public function test_housekeeping_completion_marks_room_clean_without_overwriting_maintenance(): void
    {
        $room = $this->room();
        $room->update(['operational_status' => RoomOperationalStatus::Maintenance, 'housekeeping_status' => HousekeepingStatus::Dirty]);
        $task = HousekeepingTask::create(['room_id' => $room->id, 'task_type' => 'cleaning', 'priority' => TaskPriority::High->value, 'status' => TaskStatus::Pending->value]);
        app(HousekeepingService::class)->complete($task);
        $this->assertSame(HousekeepingStatus::Clean, $room->fresh()->housekeeping_status);
        $this->assertSame(RoomOperationalStatus::Maintenance, $room->fresh()->operational_status);
    }

    public function test_room_operations_require_permissions(): void
    {
        $room = $this->room();
        $viewer = $this->user(['rooms.view']);
        $this->actingAs($viewer)->get(route('rooms.index'))->assertOk();
        $this->actingAs($viewer)->post(route('rooms.status', $room), ['operational_status' => RoomOperationalStatus::Blocked->value])->assertForbidden();
    }

    private function room(): Room
    {
        $floor = Floor::create(['name' => 'Floor 1']);
        return $this->roomOnFloor($floor);
    }

    private function roomOnFloor(Floor $floor): Room
    {
        $category = RoomCategory::create(['name' => 'Standard']);
        $type = RoomType::create(['name' => 'Standard Room', 'capacity' => 2, 'base_rate' => 95]);
        return Room::create(['room_number' => (string) random_int(100, 999), 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 95, 'capacity' => 2]);
    }

    private function user(array $permissions): User
    {
        $role = Role::create(['name' => 'role-'.random_int(1, 99999), 'label' => 'Test role']);
        $models = collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission], ['label' => $permission]));
        $role->permissions()->sync($models->pluck('id'));
        return User::create(['name' => 'Test User', 'username' => 'user'.random_int(1, 99999), 'email' => random_int(1, 99999).'@example.com', 'password' => Hash::make('secret'), 'role_id' => $role->id]);
    }
}
