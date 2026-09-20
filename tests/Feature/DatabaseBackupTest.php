<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hotel.database_backup.path' => storage_path('framework/testing/backups')]);
        File::deleteDirectory(storage_path('framework/testing/backups'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/backups'));

        parent::tearDown();
    }

    public function test_database_settings_lists_and_downloads_sql_backups(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('settings.index', ['section' => 'database']))
            ->assertOk()
            ->assertSee('Database backup')
            ->assertSee('Recent backups')
            ->assertSee('No backups created yet.');

        $response = $this->actingAs($admin)->get(route('settings.database.backup'));
        $response->assertOk()->assertHeader('content-type', 'application/sql');

        $filename = basename($response->baseResponse->getFile()->getPathname());
        $this->assertStringEndsWith('.sql', $filename);
        $this->assertFileExists($response->baseResponse->getFile()->getPathname());

        $this->actingAs($admin)
            ->get(route('settings.index', ['section' => 'database']))
            ->assertOk()
            ->assertSee($filename);

        $this->actingAs($admin)
            ->get(route('settings.database.backups.download', ['filename' => $filename]))
            ->assertOk();

        File::delete($response->baseResponse->getFile()->getPathname());
    }
}
