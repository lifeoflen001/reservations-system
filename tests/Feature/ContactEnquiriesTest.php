<?php

namespace Tests\Feature;

use App\Jobs\SendContactEnquiryNotification;
use App\Mail\ContactEnquiryNotification;
use App\Models\ContactEnquiry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContactEnquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_stores_only_the_enquiry_and_queues_notification_when_configured(): void
    {
        config()->set('hotel.contact.email', 'enquiries@example.test');
        Queue::fake();
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void { $queries[] = strtolower($query->sql); });

        $this->post(route('public.contact.submit'), $this->validPayload([
            'message' => 'Please tell me about Lodgix for our property.',
        ]))
            ->assertRedirect(route('public.contact'))
            ->assertSessionHas('success', 'Thanks. Your enquiry has been received.');

        $enquiry = ContactEnquiry::query()->firstOrFail();
        $this->assertSame('new', $enquiry->status);
        $this->assertSame('Please tell me about Lodgix for our property.', $enquiry->message);
        Queue::assertPushed(SendContactEnquiryNotification::class, fn ($job) => $job->enquiryId === $enquiry->id);

        $operationalTables = ['reservations', 'clients', 'rooms', 'payments', 'invoices', 'pos_orders', 'financial_transactions'];
        foreach ($operationalTables as $table) {
            $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, $table)), "Contact submission unexpectedly queried {$table}.");
        }
    }

    public function test_contact_form_validates_required_fields_and_email(): void
    {
        $response = $this->from(route('public.contact'))->followingRedirects()
            ->post(route('public.contact.submit'), ['name' => 'A', 'email' => 'not-an-email', 'enquiry_type' => 'pricing', 'message' => 'short'])
            ->assertOk()
            ->assertSee('id="contact-name-error"', false)
            ->assertSee('aria-describedby="contact-name-error"', false)
            ->assertSee('id="contact-email-error"', false)
            ->assertSee('aria-describedby="contact-email-error"', false);

        $this->assertDatabaseCount('contact_enquiries', 0);
    }

    public function test_honeypot_does_not_store_or_queue_spam(): void
    {
        Queue::fake();
        config()->set('hotel.contact.email', 'enquiries@example.test');

        $this->post(route('public.contact.submit'), $this->validPayload(['website' => 'https://spam.example']))
            ->assertRedirect(route('public.contact'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('contact_enquiries', 0);
        Queue::assertNothingPushed();
    }

    public function test_queued_notification_is_sent_to_the_configured_address_with_reply_to(): void
    {
        config()->set('hotel.contact.email', 'enquiries@example.test');
        Mail::fake();
        $enquiry = ContactEnquiry::query()->create($this->validPayload());

        (new SendContactEnquiryNotification($enquiry->id))->handle();

        Mail::assertSent(ContactEnquiryNotification::class, function (ContactEnquiryNotification $mail) use ($enquiry): bool {
            return $mail->hasTo('enquiries@example.test')
                && $mail->enquiry->is($enquiry)
                && $mail->envelope()->replyTo[0]->address === $enquiry->email;
        });
    }

    public function test_public_contact_submission_is_limited_to_five_per_hour(): void
    {
        Queue::fake();
        config()->set('hotel.contact.email', null);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('public.contact.submit'), $this->validPayload(['email' => "person{$attempt}@example.test"]))
                ->assertRedirect(route('public.contact'));
        }

        $this->post(route('public.contact.submit'), $this->validPayload())
            ->assertTooManyRequests();
        $this->assertDatabaseCount('contact_enquiries', 5);
    }

    public function test_enquiry_content_is_escaped_in_the_restricted_management_view(): void
    {
        $admin = $this->userWithPermissions(['contact_enquiries.view', 'contact_enquiries.manage']);
        $enquiry = ContactEnquiry::query()->create($this->validPayload([
            'message' => '<script>alert("x")</script> Please call me about Lodgix.',
            'status' => 'new',
        ]));

        $this->actingAs($admin)
            ->get(route('contact-enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("x")</script>', false);

        $this->actingAs($admin)
            ->patch(route('contact-enquiries.status', $enquiry), ['status' => 'closed'])
            ->assertRedirect(route('contact-enquiries.show', $enquiry));

        $this->assertDatabaseHas('contact_enquiries', ['id' => $enquiry->id, 'status' => 'closed']);
    }

    public function test_users_without_enquiry_permission_cannot_access_the_management_inbox(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get(route('contact-enquiries.index'))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hotel Manager',
            'company' => 'Independent Lodge',
            'email' => 'manager@example.test',
            'phone' => '+255 700 000 000',
            'country' => 'Tanzania',
            'hotel_size' => 24,
            'enquiry_type' => 'pricing',
            'message' => 'Please share more information about Lodgix.',
        ], $overrides);
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::create(['name' => 'enquiry-test-'.uniqid(), 'label' => 'Enquiry test', 'is_active' => true]);
        if ($names !== []) {
            $permissions = collect($names)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $name]));
            $role->permissions()->sync($permissions->pluck('id')->all());
        }

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
    }
}
