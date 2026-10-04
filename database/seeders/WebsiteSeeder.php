<?php

namespace Database\Seeders;

use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsitePricingFeature;
use App\Models\WebsitePricingPlan;
use App\Models\WebsiteSection;
use App\Models\WebsiteSetting;
use App\Support\WebsiteContentDefaults;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (WebsiteContentDefaults::pages() as $key => $definition) {
            $page = WebsitePage::query()->firstOrCreate(
                ['key' => $key],
                ['name' => $definition['name'], 'route_name' => $definition['route_name'], 'status' => 'published', 'seo_title' => $definition['seo_title'], 'seo_description' => $definition['seo_description'], 'robots_index' => true, 'robots_follow' => true]
            );

            $sections = $definition['sections'] ?? [];
            if (! empty($definition['hero'])) array_unshift($sections, ['key' => 'hero', 'type' => 'hero', 'content' => $definition['hero']]);
            foreach ($sections as $position => $section) {
                WebsiteSection::query()->firstOrCreate(
                    ['page_id' => $page->id, 'section_key' => $section['key']],
                    ['section_type' => $section['type'], 'position' => $position, 'is_visible' => true, 'draft_content' => $section['content'], 'published_content' => $section['content']]
                );
            }
        }

        foreach (WebsiteContentDefaults::navigation() as $item) {
            WebsiteNavigationItem::query()->firstOrCreate(['location' => $item['location'], 'destination' => $item['destination']], $item + ['status' => 'published', 'is_visible' => true]);
        }

        foreach (WebsiteContentDefaults::pricingPlans() as $position => $planData) {
            $plan = WebsitePricingPlan::query()->firstOrCreate(
                ['name' => $planData['name']],
                ['short_description' => $planData['short_description'], 'price_display' => 'Tailored pricing', 'billing_label' => null, 'cta_label' => 'Request pricing', 'cta_url' => 'public.contact', 'position' => $position, 'is_active' => true, 'status' => 'published', 'published_at' => now()]
            );
            foreach ($planData['features'] as $featurePosition => $feature) WebsitePricingFeature::query()->firstOrCreate(['plan_id' => $plan->id, 'feature' => $feature], ['included' => true, 'position' => $featurePosition]);
        }

        foreach ([
            'product_name' => config('hotel.brand.product_name', 'Lodgix'),
            'short_description' => 'Connected hotel operations from reservations to reporting.',
            'default_cta_label' => 'Contact Us',
            'footer_description' => 'Lodgix connects reservations, rooms, hotel operations, staff, POS, finance and reporting in one workspace.',
            'copyright' => '© '.now()->year.' Lodgix',
        ] as $key => $value) WebsiteSetting::query()->firstOrCreate(['key' => $key], ['value' => $value, 'type' => 'string']);
    }
}
