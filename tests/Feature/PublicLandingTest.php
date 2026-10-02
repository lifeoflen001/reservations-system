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
            'home' => ['/', 'Connected hotel workflow'],
            'product' => ['/product', 'The operating system for your hotel.'],
            'operations' => ['/operations', 'Keep every stay and every room operation in sync.'],
            'pos' => ['/pos', 'From outlet sale to guest folio.'],
            'finance' => ['/finance', 'Know where the money is — and where it moved.'],
            'security' => ['/security', 'Give every role the right level of access.'],
            'integrations' => ['/integrations', 'Connect the services behind your hotel.'],
            'pricing' => ['/pricing', 'Flexible plans built around your property.'],
            'contact' => ['/contact', 'Let’s talk about your hotel.'],
        ];
    }

    public function test_public_pages_are_short_static_pages_without_pms_shell_or_operational_queries(): void
    {
        $operationalQueries = [];
        DB::listen(static function ($query) use (&$operationalQueries): void {
            $sql = strtolower((string) $query->sql);
            $operationalTables = [
                'reservations', 'clients', 'payments', 'invoices', 'financial_', 'expenses',
                'pos_', 'rooms', 'room_', 'housekeeping_', 'maintenance_', 'tasks', 'staff',
                'users', 'notifications', 'webhook_', 'channel_',
            ];
            foreach ($operationalTables as $table) {
                if (str_contains($sql, $table)) {
                    $operationalQueries[] = $query->sql;
                    break;
                }
            }
        });

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

        $this->assertSame([], $operationalQueries, 'Public marketing pages must not query operational tenant data.');
    }

    public function test_homepage_links_to_dedicated_public_product_pages_and_stays_concise(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Connected hotel workflow')
            ->assertSee('Follow the stay from booking to report.')
            ->assertSee('href="'.route('public.operations').'"', false)
            ->assertSee('href="'.route('public.pos').'"', false)
            ->assertSee('href="'.route('public.finance').'"', false)
            ->assertSee('href="'.route('public.integrations').'"', false)
            ->assertSee('assets/images/landing/lodgix-dashboard-light.webp')
            ->assertSee('assets/images/landing/lodgix-room-planning.webp')
            ->assertDontSee('Move every stay cleanly through the front desk.')
            ->assertDontSee('Turn outlet sales into clear guest charges.')
            ->assertSee('Follow the stay from booking to report.');
        foreach (['Reservations and arrivals', 'Room planning', 'Housekeeping and maintenance', 'POS &amp; guest charges', 'Finance &amp; payments', 'Integrations'] as $label) {
            $this->assertStringContainsString($label, $this->get('/')->getContent());
        }
    }

    public function test_pricing_page_uses_contact_based_pricing_and_links_to_contact_without_offer_schema(): void
    {
        $this->get(route('public.pricing'))
            ->assertOk()
            ->assertSee('Lodgix Pricing — Hotel Management System Plans')
            ->assertSee('Flexible plans built around your property.')
            ->assertSee('Essential hotel operations')
            ->assertSee('Connected operations, POS and finance')
            ->assertSee('Request pricing')
            ->assertSee('href="'.route('public.contact', ['enquiry_type' => 'pricing']).'"', false)
            ->assertDontSee('provisional')
            ->assertDontSee('No published prices')
            ->assertDontSee('"@type":"Offer"', false)
            ->assertDontSee('$99')
            ->assertDontSee('Most Popular');
    }

    public function test_contact_page_has_its_own_metadata_and_integrations_page_links_to_enquiry(): void
    {
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('Contact Lodgix — Hotel Management System Enquiries')
            ->assertSee('Tell us what you operate and what you want Lodgix to handle.')
            ->assertSee('autocomplete="email"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('What happens next')
            ->assertSee('Implementation')
            ->assertDontSee('email alert is queued')
            ->assertDontSee('Configured contact email');

        $this->get(route('public.integrations'))
            ->assertOk()
            ->assertSee('href="'.route('public.contact', ['enquiry_type' => 'integrations']).'"', false)
            ->assertSee('Contact Us');
    }

    public function test_navigation_uses_a_compact_solutions_disclosure_and_exposes_contact(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('Solutions', false)
            ->assertSee('aria-controls="public-solutions-menu"', false)
            ->assertSee('data-solutions-toggle', false)
            ->assertSee('aria-controls="public-mobile-solutions-list"', false)
            ->assertSee('aria-label="Public navigation"', false)
            ->assertSee('href="'.route('public.contact').'"', false);

        foreach (['Operations', 'POS', 'Finance', 'Security', 'Integrations'] as $solution) {
            $response->assertSee($solution);
        }
    }

    public function test_marketing_pages_render_fixed_structured_data_and_never_show_internal_pricing_notes(): void
    {
        foreach (['/', '/product', '/operations', '/pos', '/finance', '/security', '/integrations'] as $path) {
            $this->get($path)->assertOk()->assertSee('"@type":"SoftwareApplication"', false);
        }

        $this->get('/pricing')->assertOk()->assertDontSee('conversation guides')->assertDontSee('fixed feature entitlements');
        $this->get('/contact')->assertOk()->assertDontSee('queued for delivery')->assertDontSee('notification address is configured');
    }

    public function test_marketing_screenshots_use_real_source_dimensions_and_valid_responsive_variants(): void
    {
        foreach ([
            'lodgix-dashboard-light-960.jpg' => [960, 535],
            'lodgix-dashboard-light-1440.jpg' => [1440, 802],
            'lodgix-dashboard-light.jpg' => [1654, 921],
            'lodgix-room-planning-960.jpg' => [960, 479],
            'lodgix-room-planning-1440.jpg' => [1440, 718],
            'lodgix-room-planning.jpg' => [1846, 921],
        ] as $file => [$width, $height]) {
            $image = getimagesize(public_path('assets/images/landing/'.$file));

            $this->assertSame([$width, $height], [$image[0], $image[1]], $file.' has unexpected dimensions.');
            $this->assertGreaterThan(40_000, filesize(public_path('assets/images/landing/'.$file)), $file.' should retain readable interface detail.');
        }
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
