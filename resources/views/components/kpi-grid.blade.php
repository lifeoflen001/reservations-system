@props(['items' => []])

<section class="metric-grid mini-kpi-grid" aria-label="Module summary">
    @foreach($items as $item)
        <x-kpi-card :label="$item['label']" :value="$item['value']" :icon="$item['icon'] ?? 'info'" :tone="$item['tone'] ?? 'info'" :context="$item['context'] ?? null" :href="$item['href'] ?? null" :trend="$item['trend'] ?? null" :loading="$item['loading'] ?? false" />
    @endforeach
</section>
