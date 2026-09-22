<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_form_uses_the_shared_modal_and_form_controls(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail())
            ->get(route('reservations.index', ['new' => 1]))
            ->assertOk()
            ->assertSee('data-modal-static-backdrop="true"', false)
            ->assertSee('data-pms-select-wrapper', false)
            ->assertSee('data-pms-select', false)
            ->assertSee('data-reservation-check-in', false)
            ->assertSee('data-reservation-check-out', false);
    }

    public function test_client_form_does_not_include_address_input(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail())
            ->get(route('clients.index', ['new' => 1]))
            ->assertOk()
            ->assertSee('Contact details', false)
            ->assertDontSee('name="address"', false);
    }

    public function test_unauthorized_web_requests_render_the_access_warning_modal(): void
    {
        $this->seed(DatabaseSeeder::class);
        $housekeeper = Role::query()->where('name', 'housekeeper')->firstOrFail();
        $user = User::query()->where('username', 'admin')->firstOrFail();
        $user->update(['role_id' => $housekeeper->id]);

        $this->actingAs($user)->get(route('reservations.create'))
            ->assertStatus(403)
            ->assertSee('Access restricted', false)
            ->assertSee('You have no access to perform this task.', false)
            ->assertSee('data-modal-auto-open', false)
            ->assertDontSee('This action is unauthorized.', false);
    }
}
