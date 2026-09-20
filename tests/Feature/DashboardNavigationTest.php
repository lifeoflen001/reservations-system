<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_tasks_widget_links_to_the_tasks_module(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(\App\Models\User::query()->where('username', 'admin')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('tasks.index').'"', false)
            ->assertSee('View tasks', false)
            ->assertSee('Open tasks module', false)
            ->assertSee('data-connection-status', false)
            ->assertSee('data-network-health-url="'.url('/api/health').'"', false);
    }
}
