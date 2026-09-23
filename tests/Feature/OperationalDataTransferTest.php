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

    public function test_staff_can_be_exported_as_csv_and_pdf(): void
    {
        $user = $this->user(['staff.view']);

        $csv = $this->actingAs($user)->get(route('data-transfer.export', ['resource' => 'staff', 'format' => 'csv']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Content-Disposition');

        $pdf = $this->actingAs($user)->get(route('data-transfer.export', ['resource' => 'staff', 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF');
    }

    public function test_client_csv_import_upserts_by_email(): void
    {
        $user = $this->user(['clients.view', 'clients.create']);
        $file = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'first_name,last_name,email,phone,country,nationality,is_active',
            'Asha,Mollel,asha@example.com,+255700000001,Tanzania,Tanzanian,1',
        ]));

        $this->actingAs($user)->post(route('data-transfer.import', 'clients'), ['file' => $file])->assertRedirect()->assertSessionHas('success', '1 clients imported successfully.');
        $this->assertDatabaseHas('clients', ['first_name' => 'Asha', 'last_name' => 'Mollel', 'email' => 'asha@example.com', 'nationality' => 'TZ']);

        $second = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'first_name,last_name,email,phone,country,is_active',
            'Asha,Updated,asha@example.com,+255700000002,Tanzania,1',
        ]));
        $this->actingAs($user)->post(route('data-transfer.import', 'clients'), ['file' => $second])->assertRedirect();
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('clients', ['last_name' => 'Updated', 'phone' => '+255700000002']);
    }

    public function test_bulk_template_labels_and_dates_are_normalized_across_modules(): void
    {
        $user = $this->user(['clients.view_sensitive', 'clients.create', 'rooms.create', 'staff.create', 'roles.manage', 'tasks.create', 'maintenance.create', 'housekeeping.create']);

        $this->importCsv($user, 'clients', 'first_name,last_name,email,date_of_birth,document_expiry,is_active' . "\n" . 'Asha,Mollel,asha.bulk@example.com,30/01/1958,21/01/2031,1');
        $this->importCsv($user, 'rooms', 'room_number,floor,category,room_type,operational_status,housekeeping_status,base_rate,capacity,notes,is_active' . "\n" . '1001,1,Deluxe,Double,Operational,In Progress,210000,2,Quiet wing,1');
        $this->importCsv($user, 'staff', 'username,first_name,last_name,email,phone,department,role,language,is_active' . "\n" . 'room-attendant-001,Irene,Mushi,irene.bulk@example.com,+255700000001,Housekeeping,Room Attendant,English / Swahili,1');

        $this->importCsv($user, 'tasks', 'task_number,title,description,category,status,priority,department,room,reservation_code,client_email,assignees,due_at,estimated_minutes,actual_minutes,archived' . "\n" . 'TSK-2026-00001,Prepare room,Prepare the room,Housekeeping,In Progress,High,Reservations,1001,RSV-MISSING-001,asha.bulk@example.com,room-attendant-001,2026-09-08 13:15:00,60,,0');
        $this->importCsv($user, 'maintenance', 'id,room,assignee,issue,description,priority,starts_at,ends_at,due_at,cost,status,notes,completed_at' . "\n" . ',1001,room-attendant-001,Air conditioner not cooling,Inspect the unit,Normal,2026-09-07 10:00:00,,2026-09-07 17:00:00,200000,Open,,');
        $this->importCsv($user, 'housekeeping', 'id,room,assignee,task_type,priority,due_at,status,notes,completed_at' . "\n" . ',1001,room-attendant-001,Deep Cleaning,Urgent,2026-09-12 10:45:00,In Progress,,');

        $this->assertSame('1958-01-30', \App\Models\Client::where('email', 'asha.bulk@example.com')->firstOrFail()->date_of_birth->toDateString());
        $this->assertDatabaseHas('rooms', ['room_number' => '1001', 'operational_status' => 'maintenance', 'housekeeping_status' => 'dirty']);
        $this->assertDatabaseHas('users', ['username' => 'room-attendant-001']);
        $this->assertDatabaseHas('tasks', ['task_number' => 'TSK-2026-00001', 'status' => 'in_progress', 'reservation_id' => null]);
        $this->assertDatabaseHas('maintenance_tasks', ['status' => 'pending']);
        $this->assertDatabaseHas('housekeeping_tasks', ['status' => 'in_progress']);
    }

    public function test_json_import_returns_row_level_errors_without_saving_rows(): void
    {
        $user = $this->user(['clients.view', 'clients.create']);
        $file = UploadedFile::fake()->createWithContent('clients.csv', "first_name,last_name,nationality\nAsha,Mollel,Atlantis\n");

        $this->actingAs($user)->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('data-transfer.import', 'clients'), ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.0', 'Import failed. No rows were saved.')
            ->assertJsonPath('errors.1', "Row 2: Invalid nationality 'Atlantis'. Use an ISO-2 code such as TZ, KE, RW, UG or BI, or a supported country name.");
        $this->assertDatabaseCount('clients', 0);
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

    private function importCsv(User $user, string $resource, string $contents): void
    {
        $file = UploadedFile::fake()->createWithContent($resource.'.csv', $contents);
        $this->actingAs($user)->post(route('data-transfer.import', $resource), ['file' => $file])->assertRedirect()->assertSessionHas('success');
    }
}
