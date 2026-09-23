<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Room;
use App\Models\User;
use App\Support\TablePagination;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_only_supported_rows_per_page_values_are_accepted(): void
    {
        $this->assertContains(20, TablePagination::options());
        $this->assertSame(50, TablePagination::perPage(Request::create('/?per_page=50'), 15));
        $this->assertSame(15, TablePagination::perPage(Request::create('/?per_page=500'), 15));
        $this->assertSame(15, TablePagination::perPage(Request::create('/?per_page=invalid'), 15));
    }

    public function test_clients_table_renders_rows_filter_and_preserves_current_filters(): void
    {
        foreach (range(1, 11) as $number) {
            Client::create([
                'first_name' => 'Guest',
                'last_name' => 'Number '.$number,
                'email' => 'guest'.$number.'@example.com',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs(User::query()->where('username', 'admin')->firstOrFail())
            ->get(route('clients.index', ['search' => 'Guest', 'per_page' => 10]));

        $response->assertOk()
            ->assertSee('pagination-size-form', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('value="10"', false)
            ->assertSee('name="search"', false)
            ->assertSee('value="Guest"', false)
            ->assertSee('pagination__controls', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_rooms_table_uses_the_same_rows_filter(): void
    {
        $response = $this->actingAs(User::query()->where('username', 'admin')->firstOrFail())
            ->get(route('rooms.index', ['tab' => 'list', 'per_page' => 50]));

        $response->assertOk()
            ->assertSee('pagination-size-form', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('value="50"', false);
    }
}
