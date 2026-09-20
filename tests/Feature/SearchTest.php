<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_returns_matching_pages_clients_and_rooms(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();
        $client = Client::create(['first_name' => 'Searchable', 'last_name' => 'Guest', 'email' => 'searchable@example.com', 'is_active' => true]);
        $room = Room::query()->firstOrFail();

        $this->actingAs($admin)->get(route('search', ['q' => 'Searchable']))
            ->assertOk()
            ->assertSee('Search results')
            ->assertSee('Searchable Guest')
            ->assertSee('name="q"', false);

        $this->actingAs($admin)->get(route('search', ['q' => $room->room_number]))
            ->assertOk()
            ->assertSee('Room '.$room->room_number);

        $this->actingAs($admin)->get(route('search', ['q' => 'Reports']))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Open page');
    }
}
