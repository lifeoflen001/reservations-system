<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PeopleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_clients_can_be_searched_created_archived_and_duplicate_warning_is_shown(): void
    {
        $admin = User::firstOrFail();
        $client = Client::create(['first_name' => 'Emily', 'last_name' => 'Johnson', 'email' => 'emily@example.com', 'phone' => '+1 202 555 0101', 'is_active' => true]);

        $this->actingAs($admin)->get(route('clients.index', ['search' => 'Emily']))->assertOk()->assertSee('Emily Johnson');
        $this->actingAs($admin)->post(route('clients.store'), ['first_name' => 'Emily', 'last_name' => 'Duplicate', 'email' => 'emily@example.com'])->assertRedirect(route('clients.index', ['new' => 1]))->assertSessionHas('duplicate_matches');
        $this->assertDatabaseCount('clients', 1);

        $this->actingAs($admin)->post(route('clients.store'), ['first_name' => 'Emily', 'last_name' => 'Duplicate', 'email' => 'emily@example.com', 'force' => 1])->assertRedirect();
        $this->assertDatabaseCount('clients', 2);
        $this->actingAs($admin)->delete(route('clients.destroy', $client))->assertRedirect();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'is_active' => 0]);
    }

    public function test_staff_creation_hashes_password_and_reset_requires_change(): void
    {
        $admin = User::firstOrFail();
        $role = Role::where('name', 'manager')->firstOrFail();
        $this->actingAs($admin)->post(route('staff.store'), ['first_name' => 'Ava', 'last_name' => 'Morgan', 'email' => 'ava@example.com', 'username' => 'ava', 'role_id' => $role->id, 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertRedirect();
        $staff = User::where('username', 'ava')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPass123!', $staff->password));
        $this->actingAs($admin)->post(route('staff.reset-password', $staff), ['password' => 'NewStrongPass123!', 'password_confirmation' => 'NewStrongPass123!'])->assertRedirect();
        $this->assertTrue($staff->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NewStrongPass123!', $staff->fresh()->password));
    }

    public function test_forced_password_change_redirects_before_dashboard_access(): void
    {
        $role = Role::where('name', 'front_desk')->firstOrFail();
        $user = User::create(['name' => 'Forced User', 'first_name' => 'Forced', 'last_name' => 'User', 'username' => 'forced', 'email' => 'forced@example.com', 'password' => Hash::make('OldPass123!'), 'role_id' => $role->id, 'must_change_password' => true, 'is_active' => true]);

        $this->post(route('login.store'), ['identity' => 'forced', 'password' => 'OldPass123!'])->assertRedirect(route('password.change'));
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->actingAs($user)->get(route('password.change'))->assertOk();
    }
}
