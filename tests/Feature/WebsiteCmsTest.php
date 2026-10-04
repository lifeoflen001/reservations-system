<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WebsiteSeeder::class);
    }

    public function test_website_cms_requires_authentication_and_permission(): void
    {
        $this->get(route('website.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithPermissions([]))->get(route('website.dashboard'))->assertForbidden();
    }

    public function test_website_sidebar_only_exposes_assigned_sections(): void
    {
        $user = $this->userWithPermissions(['website.view']);

        $this->actingAs($user)->get(route('website.dashboard'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertDontSee('Media library')
            ->assertDontSee('Pricing')
            ->assertDontSee('Settings');

        $manager = $this->userWithPermissions(['website.view', 'website.pages.manage', 'website.media.manage']);

        $this->actingAs($manager)->get(route('website.dashboard'))
            ->assertSee('Pages')
            ->assertSee('Media library');
    }

    public function test_authorized_website_manager_can_save_and_publish_structured_content(): void
    {
        $user = $this->userWithPermissions(['website.view', 'website.pages.manage', 'website.pages.publish']);
        $page = WebsitePage::where('key', 'home')->firstOrFail();

        $this->actingAs($user)->get(route('website.dashboard'))->assertOk()->assertSee('Published pages');
        $this->actingAs($user)->get(route('website.pages.edit', $page))->assertOk()->assertSee('Page settings');
        $this->actingAs($user)->get(route('website.pages.revisions', $page))->assertOk()->assertSee('Page revisions');
        $originalHeading = data_get($page->sections()->where('section_key', 'hero')->firstOrFail()->published_content, 'heading');
        foreach (WebsitePage::all() as $managedPage) {
            $this->actingAs($user)->get(route('website.pages.edit', $managedPage))->assertOk();
        }
        $this->actingAs($user)->patch(route('website.pages.update', $page), [
            'section_id' => $page->sections()->where('section_key', 'hero')->value('id'),
            'content' => ['heading' => 'A controlled draft heading', 'description' => 'A safe draft description.'],
        ])->assertRedirect();

        $this->assertDatabaseHas('website_sections', ['section_key' => 'hero']);
        $page->refresh()->load('sections');
        $this->assertSame('A controlled draft heading', data_get($page->sections->firstWhere('section_key', 'hero')->draft_content, 'heading'));
        $this->assertNotSame('A controlled draft heading', data_get($page->sections->firstWhere('section_key', 'hero')->published_content, 'heading'));

        $this->actingAs($user)->get(route('public.home'))->assertOk()->assertDontSee('A controlled draft heading');
        $this->actingAs($user)->post(route('website.pages.publish', $page))->assertRedirect();
        $this->get(route('public.home'))->assertOk()->assertSee('A controlled draft heading');
        $this->assertDatabaseCount('website_revisions', 1);

        $this->actingAs($user)->patch(route('website.pages.update', $page), [
            'section_id' => $page->sections()->where('section_key', 'hero')->value('id'),
            'content' => ['heading' => 'A second draft heading'],
        ])->assertRedirect();
        $this->actingAs($user)->post(route('website.pages.revisions.restore', [$page, $page->revisions()->firstOrFail()]))->assertRedirect(route('website.pages.edit', $page));
        $page->refresh()->load('sections');
        $this->assertSame($originalHeading, data_get($page->sections->firstWhere('section_key', 'hero')->draft_content, 'heading'));
    }

    public function test_cms_layouts_expose_the_correct_page_actions_and_settings_categories(): void
    {
        $user = $this->userWithPermissions([
            'website.view', 'website.pages.manage', 'website.pages.publish', 'website.seo.manage',
            'website.settings.manage', 'website.media.manage',
        ]);
        $page = WebsitePage::where('key', 'home')->firstOrFail();

        $this->actingAs($user)->get(route('website.pages.index'))
            ->assertOk()
            ->assertSee('Preview draft')
            ->assertSee('View live')
            ->assertSee('Revision history')
            ->assertSee('website-row-menu__edit-mobile', false);

        $this->actingAs($user)->get(route('website.pages.edit', $page))
            ->assertOk()
            ->assertSee('Last published')
            ->assertSee('Page settings')
            ->assertSee('Save section draft');

        $this->actingAs($user)->get(route('website.settings', ['section' => 'seo']))
            ->assertOk()
            ->assertSee('General')
            ->assertSee('Branding')
            ->assertSee('Contact')
            ->assertSee('Footer')
            ->assertSee('Social')
            ->assertSee('SEO defaults')
            ->assertSee('Open SEO manager');
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::create(['name' => 'website-test-'.uniqid(), 'label' => 'Website test', 'is_active' => true]);
        $permissions = collect($names)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        $role->permissions()->sync($permissions->pluck('id')->all());
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
    }
}
