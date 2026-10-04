<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PosCategory;
use App\Models\PosOutlet;
use App\Models\PosProduct;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\Task;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\OperationalTenantBackfillService;
use App\Services\PosOrderService;
use App\Services\ReservationService;
use App\Services\TaskService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SaasOperationalOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_operational_identifiers_are_scoped_to_property(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOrganization($propertyA->organization, $admin, 'Second Property');

        $this->useProperty($admin, $propertyA);
        $catalogA = $this->catalog('901');
        $accountA = FinancialAccount::create(['name' => 'Shared cash', 'code' => 'shared_cash', 'type' => 'cash', 'currency' => 'USD', 'is_active' => true]);
        $outletA = PosOutlet::create(['name' => 'Restaurant', 'code' => 'restaurant', 'is_active' => true]);

        $this->useProperty($admin, $propertyB);
        $this->assertSame($propertyB->id, app(TenantContext::class)->propertyId());
        $catalogB = $this->catalog('901');
        $accountB = FinancialAccount::create(['name' => 'Shared cash', 'code' => 'shared_cash', 'type' => 'cash', 'currency' => 'USD', 'is_active' => true]);
        $outletB = PosOutlet::create(['name' => 'Restaurant', 'code' => 'restaurant', 'is_active' => true]);

        $this->assertNotSame($catalogA->room->id, $catalogB->room->id);
        $this->assertNotSame($accountA->id, $accountB->id);
        $this->assertNotSame($outletA->id, $outletB->id);
        $this->assertSame($propertyA->id, $catalogA->room->property_id);
        $this->assertSame($propertyB->id, $catalogB->room->property_id);

        $this->useProperty($admin, $propertyA);
        try {
            Room::create([
                'room_number' => '901', 'floor_id' => $catalogA->floor_id, 'room_category_id' => $catalogA->room_category_id,
                'room_type_id' => $catalogA->room_type_id, 'operational_status' => RoomOperationalStatus::Available,
                'housekeeping_status' => HousekeepingStatus::Clean, 'base_rate' => 100, 'capacity' => 2,
            ]);
            $this->fail('A duplicate room number must be rejected within one property.');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf(UniqueConstraintViolationException::class, $exception);
            $this->addToAssertionCount(1);
        }
    }

    public function test_guest_can_be_shared_across_properties_in_one_organization_but_not_across_organizations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOrganization($propertyA->organization, $admin, 'Second Property');
        $this->useProperty($admin, $propertyA);
        $guest = Client::create(['first_name' => 'Shared', 'last_name' => 'Guest', 'email' => 'shared@example.com']);
        $roomA = $this->catalog('201')->room;
        $reservationA = app(ReservationService::class)->create($this->reservationData($roomA, $guest), $admin->id);

        $this->useProperty($admin, $propertyB);
        $roomB = $this->catalog('202')->room;
        $reservationB = app(ReservationService::class)->create($this->reservationData($roomB, $guest, '2026-11-01'), $admin->id);

        $this->assertSame($guest->organization_id, $reservationA->client->organization_id);
        $this->assertSame($guest->organization_id, $reservationB->client->organization_id);
        $this->assertSame($propertyA->id, $reservationA->property_id);
        $this->assertSame($propertyB->id, $reservationB->property_id);

        $otherOrganization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Other Organization', 'slug' => 'other-organization']);
        $otherGuest = Client::create(['first_name' => 'Other', 'last_name' => 'Guest', 'email' => 'other@example.com']);
        $otherGuest->forceFill(['organization_id' => $otherOrganization->id])->save();
        $this->useProperty($admin, $propertyA);

        $this->expectException(InvalidArgumentException::class);
        app(ReservationService::class)->create($this->reservationData($roomA, $otherGuest, '2026-12-01'), $admin->id);
    }

    public function test_pos_room_charge_rejects_a_reservation_from_another_property(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOrganization($propertyA->organization, $admin, 'Second Property');

        $this->useProperty($admin, $propertyA);
        $catalogA = $this->catalog('301');
        $outlet = PosOutlet::create(['name' => 'Restaurant', 'code' => 'room-charge-restaurant', 'is_active' => true]);
        $category = PosCategory::create(['name' => 'Food', 'code' => 'room-charge-food', 'is_active' => true]);
        $product = PosProduct::create(['category_id' => $category->id, 'outlet_id' => $outlet->id, 'name' => 'Dinner', 'sku' => 'ROOM-CHARGE-1', 'selling_price' => 50, 'tax_rate' => 0, 'is_active' => true]);

        $this->useProperty($admin, $propertyB);
        $catalogB = $this->catalog('302');
        $guest = Client::create(['first_name' => 'In', 'last_name' => 'House', 'email' => 'in-house@example.com']);
        $reservation = Reservation::create($this->reservationData($catalogB->room, $guest, '2026-10-20') + [
            'code' => 'CROSS-ROOM-1', 'status' => ReservationStatus::CheckedIn->value,
        ]);

        $this->useProperty($admin, $propertyA);
        $this->expectException(InvalidArgumentException::class);
        app(PosOrderService::class)->checkout([
            'outlet_id' => $outlet->id,
            'reservation_id' => $reservation->id,
            'client_id' => $guest->id,
            'room_id' => $catalogB->room->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'charge_to_room', 'amount' => 50]],
            'idempotency_key' => 'cross-property-room-charge',
        ], $admin);
    }

    public function test_finance_and_tasks_reject_cross_property_references(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOrganization($propertyA->organization, $admin, 'Second Property');

        $this->useProperty($admin, $propertyA);
        $accountA = FinancialAccount::create(['name' => 'Transfer A', 'code' => 'transfer_a', 'type' => 'cash', 'currency' => 'USD', 'is_active' => true]);
        $roomA = $this->catalog('401')->room;
        $this->useProperty($admin, $propertyB);
        $accountB = FinancialAccount::create(['name' => 'Transfer B', 'code' => 'transfer_b', 'type' => 'cash', 'currency' => 'USD', 'is_active' => true]);
        $roomB = $this->catalog('402')->room;

        $this->useProperty($admin, $propertyA);
        app(FinanceService::class)->postOpeningBalance($accountA, 100, $admin->id);
        $this->expectException(InvalidArgumentException::class);
        app(FinanceService::class)->createTransfer(['from_account_id' => $accountA->id, 'to_account_id' => $accountB->id, 'amount' => 10], $admin->id);
    }

    public function test_task_room_mismatch_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOrganization($propertyA->organization, $admin, 'Second Property');
        $this->useProperty($admin, $propertyA);
        $this->catalog('501');
        $this->useProperty($admin, $propertyB);
        $roomB = $this->catalog('502')->room;
        $this->useProperty($admin, $propertyA);

        $this->expectException(InvalidArgumentException::class);
        app(TaskService::class)->create(['title' => 'Wrong property task', 'room_id' => $roomB->id, 'status' => 'pending', 'priority' => 'medium'], $admin->id);
    }

    public function test_operational_backfill_is_idempotent_and_preserves_financial_totals(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        $before = app(OperationalTenantBackfillService::class)->run()['financial_totals'];
        DB::table('financial_accounts')->insert([
            'property_id' => $property->id,
            'name' => 'Legacy account', 'code' => 'legacy_account', 'type' => 'cash', 'currency' => 'USD', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $first = app(OperationalTenantBackfillService::class)->run();
        $after = $first['financial_totals'];
        $second = app(OperationalTenantBackfillService::class)->run();

        $this->assertSame($before, $after);
        $this->assertSame($property->id, DB::table('financial_accounts')->where('code', 'legacy_account')->value('property_id'));
        $this->assertSame(DB::table('financial_accounts')->count(), $second['tables']['financial_accounts::property_id']['existing_owned']);
        $this->assertSame(0, $second['tables']['financial_accounts::property_id']['backfilled']);
        $this->assertSame([], $second['anomalies']);
    }

    private function propertyInOrganization(Organization $organization, User $admin, string $name): Property
    {
        $property = Property::create([
            'organization_id' => $organization->id, 'name' => $name, 'uuid' => fake()->uuid(),
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(10, 99),
            'property_code' => 'PROP-'.fake()->unique()->numberBetween(10, 99), 'status' => 'active',
        ]);
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $admin->id)->firstOrFail();
        PropertyMembership::create(['membership_id' => $membership->id, 'property_id' => $property->id, 'status' => 'active']);

        return $property;
    }

    private function useProperty(User $admin, Property $property): void
    {
        $this->actingAs($admin);
        $context = app(TenantContext::class);
        $context->setOrganization($property->organization);
        $context->setProperty($property);
    }

    /** @return object{floor_id:int,room_category_id:int,room_type_id:int,room:Room} */
    private function catalog(string $roomNumber): object
    {
        $floor = Floor::create(['name' => 'Floor '.$roomNumber]);
        $category = RoomCategory::create(['name' => 'Category '.$roomNumber]);
        $type = RoomType::create(['name' => 'Type '.$roomNumber, 'capacity' => 2, 'base_rate' => 100]);
        $room = Room::create([
            'room_number' => $roomNumber, 'floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id,
            'operational_status' => RoomOperationalStatus::Available, 'housekeeping_status' => HousekeepingStatus::Clean,
            'base_rate' => 100, 'capacity' => 2,
        ]);

        return (object) ['floor_id' => $floor->id, 'room_category_id' => $category->id, 'room_type_id' => $type->id, 'room' => $room];
    }

    private function reservationData(Room $room, Client $guest, string $date = '2026-10-10'): array
    {
        return [
            'client_id' => $guest->id, 'room_id' => $room->id, 'check_in' => $date.' 14:00',
            'check_out' => date('Y-m-d', strtotime($date.' +2 days')).' 11:00', 'adults' => 1,
            'children' => 0, 'nightly_rate' => 100, 'status' => ReservationStatus::Confirmed->value,
        ];
    }
}
