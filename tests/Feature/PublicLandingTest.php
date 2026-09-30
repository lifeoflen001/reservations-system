<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public static function publicPages(): array
    {
        return [
            'home' => ['/', 'Run the daily hotel operation from one clear workspace.'],
            'product' => ['/product', 'The operating system for your hotel.'],
            'operations' => ['/operations', 'Keep every stay and every room operation in sync.'],
            'pos' => ['/pos', 'From outlet sale to guest folio.'],
            'finance' => ['/finance', 'Know where the money is — and where it moved.'],
            'security' => ['/security', 'Give every role the right level of access.'],
            'integrations' => ['/integrations', 'Connect supporting services with clear boundaries.'],
        ];
    }

    public function test_public_pages_are_short_static_pages_without_pms_shell_or_operational_queries(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void { $queries++; });

        foreach (self::publicPages() as [$path, $heading]) {
            $this->get($path)
                ->assertOk()
                ->assertSee('Lodgix')
                ->assertSee($heading)
                ->assertSee('rel="canonical"', false)
                ->assertSee('property="og:title"', false)
                ->assertSee('Skip to main content')
                ->assertDontSee('data-sidebar', false)
                ->assertDontSee('data-topbar', false)
                ->assertDontSee('Pro (Development)')
                ->assertDontSee('Finance balance')
                ->assertDontSee('INV-');
        }

        $this->assertSame(0, $queries, 'Public marketing pages must not query the operational database.');
    }

    public function test_homepage_links_to_dedicated_public_product_pages_and_stays_concise(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('One platform. Every part of the stay.')
            ->assertSee('The working tools behind the front desk.')
            ->assertSee('href="'.route('public.operations').'"', false)
            ->assertSee('href="'.route('public.pos').'"', false)
            ->assertSee('href="'.route('public.finance').'"', false)
            ->assertSee('href="'.route('public.integrations').'"', false)
            ->assertSee('assets/images/landing/lodgix-dashboard-light.webp')
            ->assertSee('assets/images/landing/lodgix-room-planning.webp')
            ->assertDontSee('Move every stay cleanly through the front desk.')
            ->assertDontSee('Turn outlet sales into clear guest charges.')
            ->assertSee('Follow the stay from booking to report.');
    }

    public function test_authenticated_users_see_dashboard_cta_without_losing_public_page_access(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        foreach (self::publicPages() as [$path]) {
            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee('Open Dashboard')
                ->assertDontSee('data-sidebar', false)
                ->assertDontSee('data-topbar', false);
        }
    }

    public function test_login_remains_available_and_dashboard_remains_protected(): void
    {
        config()->set('hotel.setup.enabled', false);

        $this->get(route('login'))->assertOk()->assertSee('Welcome back');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_public_sitemap_and_robots_only_expose_public_marketing_routes(): void
    {
        $sitemap = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach (self::publicPages() as [$path]) {
            $sitemap->assertSee('<loc>'.url($path).'</loc>', false);
        }
        $sitemap->assertDontSee('/dashboard')->assertDontSee('/finance/overview')->assertDontSee('/pos/terminal');

        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Allow: /')
            ->assertSee('Disallow: /finance/overview')
            ->assertSee('Disallow: /pos/terminal')
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertDontSee("Disallow: /finance\n")
            ->assertDontSee("Disallow: /pos\n");
    }
}
