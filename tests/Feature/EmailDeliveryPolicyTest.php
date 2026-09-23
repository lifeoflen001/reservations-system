<?php

namespace Tests\Feature;

use App\Jobs\SendHotelEmail;
use App\Models\EmailDeliveryLog;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailAddressPolicy;
use App\Services\HotelEmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailDeliveryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['hotel.email.allow_reserved_recipients' => false]);
    }

    public function test_reserved_and_placeholder_domains_are_not_deliverable(): void
    {
        $policy = app(EmailAddressPolicy::class);

        foreach (['ava@example.com', 'user@example.test', 'user.invalid', 'user@localhost'] as $address) {
            $this->assertFalse($policy->isDeliverable($address), $address);
        }

        $this->assertTrue($policy->isDeliverable('real.person@gmail.com'));
        $this->assertTrue($policy->isDeliverable('person@hotel.co.tz'));
    }

    public function test_placeholder_recipient_is_not_queued(): void
    {
        Queue::fake();

        $log = app(HotelEmailService::class)->queue('staff_invitation', 'ava@example.com', [
            'user_name' => 'Ava Morgan',
            'username' => 'ava',
            'property_name' => 'HotelDesk Property',
            'login_url' => 'https://hotel.example/login',
            'invitation_title' => 'Staff invitation',
        ]);

        $this->assertNull($log);
        $this->assertDatabaseCount('email_delivery_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_staff_form_rejects_placeholder_recipient_in_production_mode(): void
    {
        $admin = User::firstOrFail();
        $role = Role::where('name', 'manager')->firstOrFail();

        $this->actingAs($admin)->post(route('staff.store'), [
            'first_name' => 'Ava',
            'last_name' => 'Morgan',
            'email' => 'ava@example.com',
            'username' => 'ava-placeholder',
            'role_id' => $role->id,
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['username' => 'ava-placeholder']);
    }

    public function test_stale_placeholder_job_is_marked_skipped_without_provider_access(): void
    {
        $log = EmailDeliveryLog::create([
            'recipient' => 'ava@example.com',
            'subject' => 'Staff invitation',
            'template' => 'staff_invitation',
            'status' => 'queued',
            'provider' => 'smtp',
        ]);

        $job = new SendHotelEmail($log->id, []);
        $job->handle(app(\App\Services\IntegrationSettingsService::class), app(\App\Services\SafeTemplateRenderer::class), app(EmailAddressPolicy::class));

        $this->assertDatabaseHas('email_delivery_logs', [
            'id' => $log->id,
            'status' => 'skipped',
            'error_summary' => 'Recipient uses a reserved or placeholder domain.',
        ]);
    }
}
