<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_reports_page_keeps_the_reference_composition_with_empty_data(): void
    {
        $user = $this->user(['reports.view', 'reports.export', 'payments.view']);

        $response = $this->actingAs($user)->get(route('reports.index', ['from' => '2026-09-01', 'to' => '2026-09-10']));

        $response->assertOk()->assertSee('Export CSV')->assertSee('Revenue by room type')->assertSee('No reservation source data for this period.')->assertSee('No room revenue for this period.');
        $this->assertSame(5, substr_count($response->getContent(), 'report-kpi-card'));
    }

    public function test_report_layout_stays_stable_when_financial_data_is_restricted(): void
    {
        $user = $this->user(['reports.view']);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertOk()->assertSee('Revenue by room type')->assertSee('Financial data is restricted for this account.')->assertDontSee('Export CSV');
        $this->assertSame(5, substr_count($response->getContent(), 'report-kpi-card'));
    }

    private function user(array $permissions): User
    {
        $role = Role::create(['name' => 'reports-'.uniqid(), 'label' => 'Reports test role']);
        $models = collect($permissions)->map(fn (string $permission) => Permission::create(['name' => $permission, 'label' => $permission]));
        $role->permissions()->sync($models->pluck('id')->all());

        return User::create([
            'name' => 'Reports Test User', 'username' => 'reports-'.uniqid(), 'email' => uniqid().'@example.com',
            'password' => Hash::make('secret'), 'role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
