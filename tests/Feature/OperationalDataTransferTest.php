<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalDataTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_can_be_exported_as_csv_and_pdf(): void
    {
        $user = $this->user(['clients.view']);

        $csv = $this->actingAs($user)->get(route('data-transfer.export', ['resource' => 'clients', 'format' => 'csv']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Content-Disposition');

        $pdf = $this->actingAs($user)->get(route('data-transfer.export', ['resource' => 'clients', 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF');
    }

    public function test_client_csv_import_upserts_by_email(): void
    {
        $user = $this->user(['clients.view', 'clients.create']);
        $file = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'first_name,last_name,email,phone,country,is_active',
            'Asha,Mollel,asha@example.com,+255700000001,Tanzania,1',
        ]));

        $this->actingAs($user)->post(route('data-transfer.import', 'clients'), ['file' => $file])->assertRedirect()->assertSessionHas('success', '1 clients imported successfully.');
        $this->assertDatabaseHas('clients', ['first_name' => 'Asha', 'last_name' => 'Mollel', 'email' => 'asha@example.com']);

        $second = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'first_name,last_name,email,phone,country,is_active',
            'Asha,Updated,asha@example.com,+255700000002,Tanzania,1',
        ]));
        $this->actingAs($user)->post(route('data-transfer.import', 'clients'), ['file' => $second])->assertRedirect();
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('clients', ['last_name' => 'Updated', 'phone' => '+255700000002']);
    }

    public function test_import_requires_the_matching_permission_and_sensitive_client_columns_are_protected(): void
    {
        $viewer = $this->user(['clients.view']);
        $this->actingAs($viewer)->get(route('data-transfer.export', ['resource' => 'clients', 'format' => 'csv']))->assertOk();
        $this->actingAs($viewer)->get(route('data-transfer.template', 'clients'))->assertForbidden();

        $file = UploadedFile::fake()->createWithContent('clients.csv', "first_name,last_name,document_number\nAsha,Mollel,SECRET\n");
        $importer = $this->user(['clients.view', 'clients.create']);
        $this->actingAs($importer)->post(route('data-transfer.import', 'clients'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('clients', 0);
    }

    private function user(array $permissions): User
    {
        $role = Role::create(['name' => 'transfer-'.uniqid(), 'label' => 'Transfer test role']);
        $models = collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission], ['label' => $permission]));
        $role->permissions()->sync($models->pluck('id')->all());

        return User::create([
            'name' => 'Transfer Test User', 'first_name' => 'Transfer', 'last_name' => 'User',
            'username' => 'transfer-'.uniqid(), 'email' => uniqid().'@example.com',
            'password' => Hash::make('secret'), 'role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
