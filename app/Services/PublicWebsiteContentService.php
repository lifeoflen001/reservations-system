<?php

namespace App\Services;

use App\Models\WebsitePage;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePricingPlan;
use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Throwable;

class PublicWebsiteContentService
{
    public function publishedPage(string $key): ?WebsitePage
    {
        try {
            return Cache::remember("website.page.{$key}", now()->addMinutes(10), fn () => WebsitePage::query()->with(['sections', 'ogMedia'])->where('key', $key)->where('status', 'published')->first());
        } catch (Throwable) {
            return null;
        }
    }

    public function page(string $key): ?array
    {
        $page = $this->publishedPage($key);
        if (! $page) return null;

        return [
            'page' => $page,
            'sections' => $page->sections->where('is_visible', true)->sortBy('position')->mapWithKeys(fn ($section) => [$section->section_key => $section->published_content ?: []])->all(),
        ];
    }

    public function mergeMarketingPage(string $key, array $fallback): array
    {
        $cms = $this->page($key);
        if (! $cms) return $fallback;
        $page = $cms['page'];
        $hero = $cms['sections']['hero'] ?? [];
        foreach (['eyebrow', 'heading', 'description'] as $field) if (filled($hero[$field] ?? null)) $fallback[$field] = $hero[$field];
        $fallback['title'] = $page->seo_title ?: $fallback['title'];
        $fallback['description'] = $page->seo_description ?: $fallback['description'];
        $sections = collect($fallback['sections'] ?? []);
        foreach ($sections as $index => $section) {
            $keyForSection = array_keys($cms['sections'])[array_key_exists($index, array_keys($cms['sections'])) ? $index + 1 : 0] ?? null;
            $content = $keyForSection ? ($cms['sections'][$keyForSection] ?? null) : null;
            if (! is_array($content)) continue;
            $sections[$index] = array_merge($section, collect($content)->only(['eyebrow', 'heading', 'description', 'image', 'alt'])->all());
        }
        $fallback['sections'] = $sections->values()->all();
        return $fallback;
    }

    public function settings(): array
    {
        try {
            return Cache::remember('website.settings', now()->addMinutes(10), fn () => WebsiteSetting::query()->get()->mapWithKeys(fn ($setting) => [$setting->key => $this->decode($setting->value, $setting->type)])->all());
        } catch (Throwable) {
            return [];
        }
    }

    public function setting(string $key, mixed $default = null): mixed { return $this->settings()[$key] ?? $default; }

    public function navigation(string $location = 'header'): array
    {
        try {
            return Cache::remember("website.navigation.{$location}", now()->addMinutes(10), fn () => WebsiteNavigationItem::query()
                ->where('location', $location)
                ->where('is_visible', true)
                ->where('status', 'published')
                ->orderBy('position')
                ->get()
                ->map(fn ($item) => [
                    'label' => $item->label,
                    'destination_type' => $item->destination_type,
                    'destination' => $item->destination,
                    'url' => $item->destination_type === 'group' ? null : $this->destinationUrl($item->destination_type, $item->destination),
                ])->all());
        } catch (Throwable) {
            return [];
        }
    }

    public function pricingPlans(): ?array
    {
        try {
            $plans = Cache::remember('website.pricing.plans', now()->addMinutes(10), fn () => WebsitePricingPlan::query()->with('features')->where('status', 'published')->where('is_active', true)->orderBy('position')->get()->map(fn ($plan) => [
            'name' => $plan->name, 'short_description' => $plan->short_description, 'price_display' => $plan->price_display,
            'billing_label' => $plan->billing_label, 'cta_label' => $plan->cta_label, 'cta_url' => $this->destinationUrl('route', $plan->cta_url ?: 'public.contact'),
            'is_highlighted' => $plan->is_highlighted, 'features' => $plan->features->where('included', true)->pluck('feature')->all(),
            ])->all());
            return $plans ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function invalidate(?string $pageKey = null): void
    {
        Cache::forget('website.settings');
        Cache::forget('website.pricing.plans');
        Cache::forget('website.navigation.header');
        if ($pageKey) Cache::forget("website.page.{$pageKey}");
        else foreach (array_keys(\App\Support\WebsiteContentDefaults::pages()) as $key) Cache::forget("website.page.{$key}");
    }

    public function destinationUrl(string $type, string $destination): ?string
    {
        if ($type === 'external') return filter_var($destination, FILTER_VALIDATE_URL) ? $destination : null;
        if ($type === 'anchor') return str_starts_with($destination, '#') ? $destination : null;
        return Route::has($destination) && str_starts_with($destination, 'public.') ? route($destination) : null;
    }

    private function decode(?string $value, string $type): mixed
    {
        return $type === 'json' ? json_decode((string) $value, true) : $value;
    }
}
