<?php

namespace Tests\Feature;

use App\Mail\NewsletterSubscriptionConfirmation;
use App\Models\NewsletterSubscriber;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_subscription_normalizes_email_and_activates_without_double_opt_in(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => '  Hotel.Manager@Example.COM '])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status', 'success');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'hotel.manager@example.com',
            'status' => NewsletterSubscriber::STATUS_ACTIVE,
            'source' => 'footer',
        ]);
    }

    public function test_subscription_validates_email_and_does_not_store_honeypot_submissions(): void
    {
        $this->from(route('public.home'))->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('newsletter', ['email']);
        $this->assertDatabaseCount('newsletter_subscribers', 0);

        $this->post(route('newsletter.subscribe'), ['email' => 'bot@example.com', 'website' => 'https://spam.example'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status', 'success');
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_duplicate_and_resubscribe_flows_are_safe(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'guest@example.com']);
        $this->post(route('newsletter.subscribe'), ['email' => 'GUEST@example.com'])
            ->assertSessionHas('newsletter_status', 'already');

        $subscriber = NewsletterSubscriber::query()->firstOrFail();
        $subscriber->update(['status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);

        $this->post(route('newsletter.subscribe'), ['email' => 'guest@example.com'])
            ->assertSessionHas('newsletter_status', 'success');
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id, 'status' => NewsletterSubscriber::STATUS_ACTIVE, 'unsubscribed_at' => null]);
    }

    public function test_double_opt_in_sends_confirmation_and_confirmation_activates_subscriber(): void
    {
        config()->set('hotel.newsletter.double_opt_in', true);
        Mail::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'confirm@example.com'])
            ->assertSessionHas('newsletter_status', 'pending');
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'confirm@example.com', 'status' => NewsletterSubscriber::STATUS_PENDING]);

        $confirmationUrl = null;
        Mail::assertQueued(NewsletterSubscriptionConfirmation::class, function (NewsletterSubscriptionConfirmation $mail) use (&$confirmationUrl): bool {
            $confirmationUrl = $mail->confirmationUrl;

            return $mail->hasTo('confirm@example.com');
        });

        $this->get($confirmationUrl)->assertOk()->assertSee('Subscription confirmed');
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'confirm@example.com', 'status' => NewsletterSubscriber::STATUS_ACTIVE, 'verification_token' => null]);
    }

    public function test_unsubscribe_uses_a_random_token_and_invalid_tokens_fail_safely(): void
    {
        $rawToken = str_repeat('unsubscribe-token-', 4);
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'active@example.com',
            'status' => NewsletterSubscriber::STATUS_ACTIVE,
            'source' => 'footer',
            'subscribed_at' => now(),
            'confirmed_at' => now(),
            'unsubscribe_token' => hash('sha256', $rawToken),
        ]);

        $this->get(route('newsletter.unsubscribe', $rawToken))->assertOk()->assertSee('You are unsubscribed');
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id, 'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED]);
        $this->get(route('newsletter.unsubscribe', 'invalid-token'))->assertNotFound();
    }

    public function test_public_subscription_is_limited_to_five_requests_per_minute(): void
    {
        $key = hash_hmac('sha256', '127.0.0.1', (string) config('app.key'));
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('newsletter.subscribe'), ['email' => "person{$attempt}@example.test"])->assertRedirect();
        }

        $this->post(route('newsletter.subscribe'), ['email' => 'person5@example.test'])->assertTooManyRequests();
    }

    public function test_footer_contains_a_progressive_newsletter_form_without_nonexistent_legal_links(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('action="'.route('newsletter.subscribe').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('data-newsletter-form', false)
            ->assertSee('Work email address')
            ->assertDontSee('Privacy Policy')
            ->assertDontSee('Resources');
    }

    public function test_newsletter_admin_requires_permission_and_can_export_active_subscribers(): void
    {
        $this->get(route('newsletter.index'))->assertRedirect(route('login'));

        $user = $this->userWithPermissions(['newsletter.view', 'newsletter.manage', 'newsletter.export']);
        NewsletterSubscriber::query()->create([
            'email' => 'active@example.com', 'status' => NewsletterSubscriber::STATUS_ACTIVE, 'source' => 'footer',
            'subscribed_at' => now(), 'confirmed_at' => now(), 'unsubscribe_token' => hash('sha256', 'active-token'),
            'ip_hash' => 'should-not-export',
        ]);
        $pending = NewsletterSubscriber::query()->create([
            'email' => 'pending@example.com', 'status' => NewsletterSubscriber::STATUS_PENDING, 'source' => 'footer',
            'unsubscribe_token' => hash('sha256', 'pending-token'),
        ]);

        $this->actingAs($user)->get(route('newsletter.index'))->assertOk()->assertSee('Newsletter subscribers')->assertSee('active@example.com');
        $this->actingAs($user)->patch(route('newsletter.status', $pending), ['status' => 'active'])->assertRedirect(route('newsletter.index'));
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $pending->id, 'status' => NewsletterSubscriber::STATUS_ACTIVE]);
        $this->actingAs($user)->delete(route('newsletter.destroy', $pending))->assertRedirect(route('newsletter.index'));
        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $pending->id]);

        $export = $this->actingAs($user)->get(route('newsletter.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('active@example.com', $csv);
        $this->assertStringNotContainsString('pending@example.com', $csv);
        $this->assertStringNotContainsString('should-not-export', $csv);
    }

    public function test_users_without_newsletter_permission_cannot_access_management(): void
    {
        $this->actingAs($this->userWithPermissions([]))->get(route('newsletter.index'))->assertForbidden();
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::create(['name' => 'newsletter-test-'.uniqid(), 'label' => 'Newsletter test', 'is_active' => true]);
        $permissions = collect($names)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        $role->permissions()->sync($permissions->pluck('id')->all());

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
    }
}
