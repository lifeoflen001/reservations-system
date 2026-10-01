<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\WebsiteAuditLog;
use App\Models\WebsiteMedia;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsitePricingFeature;
use App\Models\WebsitePricingPlan;
use App\Models\WebsiteRevision;
use App\Models\WebsiteSection;
use App\Models\WebsiteSetting;
use App\Services\PublicWebsiteContentService;
use App\Support\WebsiteContentDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(private readonly PublicWebsiteContentService $content) {}

    public function dashboard(): View
    {
        return view('website.dashboard', [
            'pages' => WebsitePage::query()->with('sections')->orderBy('name')->get(),
            'publishedPages' => WebsitePage::where('status', 'published')->count(),
            'draftChanges' => WebsitePage::with('sections')->get()->filter->hasDraftChanges()->count(),
            'mediaAssets' => WebsiteMedia::whereNull('archived_at')->count(),
            'newEnquiries' => ContactEnquiry::where('status', 'new')->count(),
            'lastPublished' => WebsitePage::whereNotNull('published_at')->latest('published_at')->first(),
            'seoIssues' => WebsitePage::where(fn ($q) => $q->whereNull('seo_title')->orWhereNull('seo_description'))->count(),
            'recentEnquiries' => ContactEnquiry::latest()->limit(5)->get(),
        ]);
    }

    public function pages(): View { return view('website.pages.index', ['pages' => WebsitePage::with(['publisher', 'sections'])->orderBy('name')->get()]); }

    public function edit(WebsitePage $websitePage): View
    {
        $websitePage->load(['sections', 'publisher', 'draftEditor', 'revisions.creator']);
        return view('website.pages.edit', ['page' => $websitePage, 'media' => WebsiteMedia::whereNull('archived_at')->latest()->limit(40)->get()]);
    }

    public function update(Request $request, WebsitePage $websitePage): RedirectResponse
    {
        $data = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string', 'max:320'],
            'og_title' => ['nullable', 'string', 'max:255'], 'og_description' => ['nullable', 'string', 'max:320'],
            'robots_index' => ['sometimes', 'boolean'], 'robots_follow' => ['sometimes', 'boolean'],
            'section_id' => ['nullable', 'integer', Rule::exists('website_sections', 'id')->where('page_id', $websitePage->id)],
            'content' => ['nullable', 'array'],
            'content.eyebrow' => ['nullable', 'string', 'max:160'], 'content.heading' => ['nullable', 'string', 'max:255'],
            'content.description' => ['nullable', 'string', 'max:1000'], 'content.primary_cta_label' => ['nullable', 'string', 'max:120'],
            'content.primary_cta_url' => ['nullable', 'string', 'max:500'], 'content.image' => ['nullable', 'string', 'max:500'],
            'content.alt' => ['nullable', 'string', 'max:255'], 'content.visible' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $websitePage, $data): void {
            $pageUpdates = ['draft_updated_by' => $request->user()->id];
            foreach (['seo_title', 'seo_description', 'og_title', 'og_description'] as $field) if (array_key_exists($field, $data)) $pageUpdates[$field] = $data[$field];
            if ($request->has('robots_index')) $pageUpdates['robots_index'] = $request->boolean('robots_index');
            if ($request->has('robots_follow')) $pageUpdates['robots_follow'] = $request->boolean('robots_follow');
            $websitePage->update($pageUpdates);
            if (! empty($data['section_id'])) {
                $section = $websitePage->sections()->whereKey($data['section_id'])->firstOrFail();
                $content = collect($data['content'] ?? [])->filter(fn ($value) => $value !== null)->all();
                $section->update(['draft_content' => array_merge($section->draft_content ?? [], $content), 'draft_updated_by' => $request->user()->id, 'is_visible' => $request->boolean('content.visible', $section->is_visible)]);
            }
            $this->audit($request, 'page.draft_saved', $websitePage);
        });

        $this->content->invalidate($websitePage->key);
        return back()->with('success', 'Website draft saved.');
    }

    public function publish(Request $request, WebsitePage $websitePage): RedirectResponse
    {
        $websitePage->load('sections');
        abort_unless(filled(data_get($websitePage->sections->firstWhere('section_key', 'hero'), 'draft_content.heading')), 422, 'A page hero heading is required before publishing.');
        DB::transaction(function () use ($request, $websitePage): void {
            $nextVersion = (int) $websitePage->version + 1;
            WebsiteRevision::create(['page_id' => $websitePage->id, 'version' => $websitePage->version, 'snapshot' => ['page' => $websitePage->only(['seo_title', 'seo_description', 'og_title', 'og_description', 'robots_index', 'robots_follow']), 'sections' => $websitePage->sections->mapWithKeys(fn ($section) => [$section->section_key => $section->published_content])->all()], 'created_by' => $request->user()->id]);
            foreach ($websitePage->sections as $section) $section->update(['published_content' => $section->draft_content, 'published_by' => $request->user()->id, 'published_at' => now()]);
            $websitePage->update(['status' => 'published', 'published_at' => now(), 'published_by' => $request->user()->id, 'version' => $nextVersion]);
            $this->audit($request, 'page.published', $websitePage, ['version' => $nextVersion]);
        });
        $this->content->invalidate($websitePage->key);
        return back()->with('success', 'Website page published.');
    }

    public function preview(WebsitePage $websitePage): View
    {
        $websitePage->load('sections');
        return view('website.pages.preview', ['page' => $websitePage]);
    }

    public function revisions(WebsitePage $websitePage): View { return view('website.pages.revisions', ['page' => $websitePage->load('revisions.creator')]); }

    public function restoreRevision(Request $request, WebsitePage $websitePage, WebsiteRevision $websiteRevision): RedirectResponse
    {
        abort_unless($websiteRevision->page_id === $websitePage->id, 404);
        $snapshot = $websiteRevision->snapshot ?? [];

        DB::transaction(function () use ($request, $websitePage, $snapshot, $websiteRevision): void {
            $websitePage->update(array_merge(
                collect($snapshot['page'] ?? [])->only(['seo_title', 'seo_description', 'og_title', 'og_description', 'robots_index', 'robots_follow'])->all(),
                ['draft_updated_by' => $request->user()->id]
            ));
            foreach (($snapshot['sections'] ?? []) as $sectionKey => $content) {
                $websitePage->sections()->where('section_key', $sectionKey)->update(['draft_content' => $content, 'draft_updated_by' => $request->user()->id]);
            }
            $this->audit($request, 'page.revision_restored', $websitePage, ['revision_id' => $websiteRevision->id, 'revision_version' => $websiteRevision->version]);
        });

        $this->content->invalidate($websitePage->key);
        return redirect()->route('website.pages.edit', $websitePage)->with('success', 'Revision restored as a draft.');
    }

    public function moveSection(Request $request, WebsitePage $websitePage, WebsiteSection $websiteSection, string $direction): RedirectResponse
    {
        abort_unless($websiteSection->page_id === $websitePage->id, 404);
        $offset = $direction === 'up' ? -1 : 1;
        $neighbour = WebsiteSection::query()->where('page_id', $websitePage->id)->where('position', $websiteSection->position + $offset)->first();
        if ($neighbour) {
            DB::transaction(function () use ($request, $websiteSection, $neighbour): void {
                [$websiteSection->position, $neighbour->position] = [$neighbour->position, $websiteSection->position];
                $websiteSection->save();
                $neighbour->save();
                $this->audit($request, 'page.section_reordered', $websiteSection, ['direction' => $request->route('direction')]);
            });
        }
        return back()->with('success', 'Section order updated.');
    }

    public function media(Request $request): View
    {
        $media = WebsiteMedia::query()->with('uploader')->whereNull('archived_at')->when($request->filled('search'), fn ($q) => $q->where('original_filename', 'like', '%'.trim($request->string('search')).'%'))->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))->latest()->paginate(24)->withQueryString();
        $view = in_array($request->string('view')->toString(), ['grid', 'list'], true)
            ? $request->string('view')->toString()
            : 'grid';

        return view('website.media.index', compact('media', 'view'));
    }

    public function uploadMedia(Request $request): RedirectResponse
    {
        $data = $request->validate(['media' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'category' => ['required', Rule::in(['hero', 'screenshot', 'logo', 'icon', 'general'])], 'alt_text' => ['nullable', 'string', 'max:255'], 'caption' => ['nullable', 'string', 'max:500']]);
        $file = $data['media'];
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('website-media', $filename, 'public');
        [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];
        WebsiteMedia::create(['category' => $data['category'], 'disk' => 'public', 'path' => $path, 'filename' => $filename, 'original_filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'width' => $width, 'height' => $height, 'file_size' => $file->getSize(), 'alt_text' => $data['alt_text'] ?? null, 'caption' => $data['caption'] ?? null, 'uploaded_by' => $request->user()->id]);
        return back()->with('success', 'Website media uploaded.');
    }

    public function archiveMedia(Request $request, WebsiteMedia $websiteMedia): RedirectResponse
    {
        abort_if($websiteMedia->pages()->exists(), 422, 'Media in use cannot be archived until it is replaced.');
        $websiteMedia->update(['archived_at' => now()]);
        $this->audit($request, 'media.archived', $websiteMedia);
        return back()->with('success', 'Media archived.');
    }

    public function navigation(): View { return view('website.navigation', ['items' => WebsiteNavigationItem::with('children')->whereNull('parent_id')->orderBy('position')->get()]); }

    public function updateNavigation(Request $request): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array'], 'items.*.id' => ['required', 'integer', 'exists:website_navigation_items,id'], 'items.*.label' => ['required', 'string', 'max:120'], 'items.*.is_visible' => ['sometimes', 'boolean']]);
        foreach ($data['items'] as $item) WebsiteNavigationItem::whereKey($item['id'])->update(['label' => $item['label'], 'is_visible' => (bool) ($item['is_visible'] ?? false)]);
        $this->audit($request, 'navigation.updated', null);
        return back()->with('success', 'Website navigation updated.');
    }

    public function pricing(Request $request): View
    {
        $plans = WebsitePricingPlan::with('features')->orderBy('position')->get();
        $selectedPlan = $request->filled('edit')
            ? $plans->firstWhere('id', $request->integer('edit'))
            : null;

        return view('website.pricing', compact('plans', 'selectedPlan'));
    }

    public function seo(Request $request): View
    {
        $pages = WebsitePage::query()->with('ogMedia')->orderBy('name')->get();
        $selectedPage = $request->filled('edit')
            ? $pages->firstWhere('id', $request->integer('edit'))
            : null;

        return view('website.seo', compact('pages', 'selectedPage'));
    }

    public function updatePricing(Request $request, WebsitePricingPlan $websitePricingPlan): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'short_description' => ['nullable', 'string', 'max:500'], 'price_display' => ['required', 'string', 'max:120'], 'cta_label' => ['required', 'string', 'max:120'], 'cta_url' => ['nullable', 'string', 'max:500'], 'is_highlighted' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean']]);
        $websitePricingPlan->update($data + ['updated_by' => $request->user()->id]);
        $this->audit($request, 'pricing.changed', $websitePricingPlan);
        return back()->with('success', 'Pricing plan updated.');
    }

    public function settings(Request $request): View
    {
        $section = $request->string('section')->toString();
        $section = in_array($section, ['general', 'branding', 'contact', 'footer', 'social', 'seo'], true) ? $section : 'general';

        return view('website.settings', [
            'settings' => WebsiteSetting::orderBy('key')->get(),
            'section' => $section,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_name' => ['required', 'string', 'max:120'], 'short_description' => ['nullable', 'string', 'max:320'], 'default_cta_label' => ['nullable', 'string', 'max:120'], 'footer_description' => ['nullable', 'string', 'max:500'], 'copyright' => ['nullable', 'string', 'max:255'], 'support_email' => ['nullable', 'email', 'max:254'], 'contact_email' => ['nullable', 'email', 'max:254']]);
        foreach ($data as $key => $value) WebsiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string', 'updated_by' => $request->user()->id]);
        $this->audit($request, 'settings.updated');
        $this->content->invalidate();
        return back()->with('success', 'Website settings updated.');
    }

    private function audit(Request $request, string $action, object|null $target = null, array $metadata = []): void
    {
        WebsiteAuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'target_type' => $target ? $target::class : null, 'target_id' => $target?->getKey(), 'metadata' => $metadata ?: null]);
    }
}
