<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_publish_announcement_and_recipients_receive_in_app_notification(): void
    {
        [$admin, $staff] = $this->users();
        $response = $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Policy update',
            'category' => 'Policy Updates',
            'short_description' => 'Please review the updated policy.',
            'content' => '<p>Read this <strong>important</strong> update.</p><script>alert(1)</script>',
            'start_at' => now()->format('Y-m-d H:i'),
            'is_company_wide' => '1',
            'publish_now' => '1',
        ]);

        $announcement = Announcement::query()->firstOrFail();
        $response->assertRedirect(route('announcements.show', $announcement));
        $this->assertSame('active', $announcement->status);
        $this->assertStringNotContainsString('<script', $announcement->content);
        $this->assertDatabaseHas('announcement_recipients', ['announcement_id' => $announcement->id, 'user_id' => $staff->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);
    }

    public function test_department_audience_is_snapshotted_and_stats_record_views(): void
    {
        [$admin, $staff] = $this->users();
        $department = Department::create(['name' => 'Front Office', 'is_active' => true, 'sort_order' => 1]);
        $staff->update(['department_id' => $department->id]);
        $announcement = Announcement::create([
            'title' => 'Front office briefing', 'category' => 'Events', 'short_description' => 'Briefing', 'content' => '<p>Briefing</p>',
            'start_at' => now(), 'status' => 'draft', 'is_company_wide' => false, 'created_by' => $admin->id,
        ]);
        $announcement->departments()->attach($department);

        $this->actingAs($admin)->post(route('announcements.publish', $announcement))->assertRedirect();
        $this->actingAs($staff)->get(route('announcements.show', $announcement))->assertOk();
        $this->assertDatabaseHas('announcement_recipients', ['announcement_id' => $announcement->id, 'user_id' => $staff->id, 'view_count' => 1]);
        $this->actingAs($admin)->get(route('announcements.statistics', $announcement))->assertOk()->assertSee('100%');
    }

    public function test_private_attachments_are_stored_and_download_requires_announcement_access(): void
    {
        Storage::fake('local');
        [$admin] = $this->users();
        $response = $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Attachment update', 'category' => 'General', 'short_description' => 'File attached',
            'content' => '<p>See the file.</p>', 'start_at' => now()->format('Y-m-d H:i'), 'is_company_wide' => '1',
            'attachments' => [UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf')],
        ]);
        $announcement = Announcement::query()->firstOrFail();
        $attachment = $announcement->attachments()->firstOrFail();
        $response->assertRedirect(route('announcements.show', $announcement));
        Storage::disk('local')->assertExists($attachment->stored_name);
        $this->actingAs($admin)->get(route('announcements.attachments.download', [$announcement, $attachment]))->assertOk();
    }

    /** @return array{0: User, 1: User} */
    private function users(): array
    {
        $permissionNames = ['announcements.view', 'announcements.create', 'announcements.update', 'announcements.publish', 'announcements.archive', 'announcements.statistics', 'announcements.manage'];
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        $role = Role::create(['name' => 'announcement-admin-'.uniqid(), 'label' => 'Announcement Admin', 'is_active' => true]);
        $role->permissions()->sync($permissions->pluck('id')->all());
        $staffRole = Role::create(['name' => 'announcement-staff-'.uniqid(), 'label' => 'Announcement Staff', 'is_active' => true]);
        $staffRole->permissions()->attach(Permission::where('name', 'announcements.view')->value('id'));
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $staff = User::factory()->create(['role_id' => $staffRole->id, 'is_active' => true]);
        return [$admin, $staff];
    }
}
