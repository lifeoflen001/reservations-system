<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_export_is_authorized_and_applies_active_filters(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $account = FinancialAccount::where('code', 'cash')->firstOrFail();
        FinancialTransaction::create(['transaction_number' => 'EXP-MATCH', 'account_id' => $account->id, 'transaction_type' => 'guest_payment', 'direction' => 'credit', 'amount' => 25, 'currency' => 'USD', 'description' => 'Included payment', 'transaction_date' => now(), 'status' => 'posted']);
        FinancialTransaction::create(['transaction_number' => 'EXP-OLD', 'account_id' => $account->id, 'transaction_type' => 'guest_payment', 'direction' => 'credit', 'amount' => 99, 'currency' => 'USD', 'description' => 'Excluded payment', 'transaction_date' => now()->subDays(10), 'status' => 'posted']);

        $response = $this->actingAs($admin)->get(route('finance.exports', ['report' => 'transactions', 'format' => 'csv', 'from' => now()->toDateString(), 'to' => now()->toDateString(), 'account_id' => $account->id]));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('EXP-MATCH', $csv);
        $this->assertStringNotContainsString('EXP-OLD', $csv);
    }

    public function test_finance_export_requires_export_permission(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->user(['finance.view']);

        $this->actingAs($user)->get(route('finance.exports', ['report' => 'transactions', 'format' => 'csv']))->assertForbidden();
    }

    public function test_all_finance_export_scopes_stream_csv_headers(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();

        foreach (['transactions', 'expenses', 'accounts', 'account-statements', 'transfers', 'cash-flow', 'petty-cash', 'reconciliation', 'daily-cash', 'monthly-summary'] as $report) {
            $response = $this->actingAs($admin)->get(route('finance.exports', ['report' => $report, 'format' => 'csv']));
            $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $this->assertStringContainsString(',', $response->streamedContent(), $report.' should emit a CSV header.');
        }
    }

    private function user(array $permissions): User
    {
        $role = Role::create(['name' => 'finance-export-'.uniqid(), 'label' => 'Finance export test role']);
        $models = collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission], ['label' => $permission]));
        $role->permissions()->sync($models->pluck('id')->all());

        return User::create(['name' => 'Finance Export User', 'username' => 'finance-export-'.uniqid(), 'email' => uniqid().'@example.com', 'password' => Hash::make('secret'), 'role_id' => $role->id, 'is_active' => true]);
    }
}
