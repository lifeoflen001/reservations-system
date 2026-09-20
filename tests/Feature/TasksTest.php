<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_complete_a_task_with_relations(): void
    {
        $department = Department::create(['name' => 'Front Desk', 'is_active' => true]);
        $user = $this->user(['tasks.view', 'tasks.create', 'tasks.update', 'tasks.complete', 'tasks.comment', 'tasks.track_time']);
        $user->update(['department_id' => $department->id]);

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Prepare VIP arrival',
            'department_id' => $department->id,
            'status' => TaskStatus::New->value,
            'priority' => TaskPriority::High->value,
            'due_at' => '2026-10-01 14:00',
            'tags_text' => 'VIP, Arrival',
            'subtasks' => ['Confirm welcome amenity', 'Inspect room'],
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('TSK-000001', $task->task_number);
        $this->assertSame(TaskStatus::New, $task->status);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertCount(2, $task->subtasks);
        $this->assertCount(2, $task->tags);
        $this->actingAs($user)->get(route('tasks.show', $task))->assertOk()->assertSee('Prepare VIP arrival')->assertSee('TSK-000001');

        $this->actingAs($user)->post(route('tasks.comments.store', $task), ['body' => 'VIP preparation started.'])->assertRedirect();
        $this->actingAs($user)->post(route('tasks.subtasks.store', $task), ['title' => 'Call guest'])->assertRedirect();
        $this->actingAs($user)->post(route('tasks.timer.start', $task))->assertRedirect();
        $this->assertDatabaseHas('task_time_entries', ['task_id' => $task->id, 'user_id' => $user->id]);
        $this->actingAs($user)->post(route('tasks.timer.stop', $task))->assertRedirect();
        $this->actingAs($user)->post(route('tasks.complete', $task))->assertRedirect();

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertDatabaseHas('task_activity', ['task_id' => $task->id, 'event' => 'Task completed']);
    }

    public function test_task_visibility_prevents_cross_department_idor(): void
    {
        $frontDesk = Department::create(['name' => 'Front Desk', 'is_active' => true]);
        $maintenance = Department::create(['name' => 'Maintenance', 'is_active' => true]);
        $user = $this->user(['tasks.view', 'tasks.update']);
        $user->update(['department_id' => $frontDesk->id]);
        $other = $this->user(['tasks.view', 'tasks.create']);
        $other->update(['department_id' => $maintenance->id]);
        $task = Task::create(['task_number' => 'TSK-000001', 'title' => 'Restricted maintenance work', 'department_id' => $maintenance->id, 'created_by' => $other->id, 'status' => TaskStatus::Pending, 'priority' => TaskPriority::Normal]);

        $this->actingAs($user)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($user)->put(route('tasks.update', $task), ['title' => 'Tampered', 'status' => TaskStatus::Pending->value, 'priority' => TaskPriority::Normal->value])->assertForbidden();
        $this->actingAs($user)->get(route('tasks.index'))->assertOk()->assertDontSee($task->task_number);
    }

    public function test_new_task_form_uses_a_dedicated_draft_and_does_not_reuse_task_edit_data(): void
    {
        $user = $this->user(['tasks.view', 'tasks.create', 'tasks.update']);
        $task = Task::create([
            'task_number' => 'TSK-000001',
            'title' => 'Completed task data',
            'created_by' => $user->id,
            'status' => TaskStatus::Completed,
            'priority' => TaskPriority::Normal,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['new' => 1]))
            ->assertOk()
            ->assertSee('data-draft-key="task-new"', false)
            ->assertSee('data-draft-lifecycle="task"', false)
            ->assertSee('data-draft-status', false);

        $this->actingAs($user)
            ->get(route('tasks.index', ['edit' => $task->id]))
            ->assertOk()
            ->assertSee('data-draft-key="task-edit-'.$task->id.'"', false);
    }

    private function user(array $permissions): User
    {
        $role = Role::create(['name' => 'task-role-'.uniqid(), 'label' => 'Task role']);
        $permissionModels = collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission], ['label' => $permission]));
        $role->permissions()->sync($permissionModels->pluck('id'));

        return User::create([
            'name' => 'Task User',
            'username' => 'task-'.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
