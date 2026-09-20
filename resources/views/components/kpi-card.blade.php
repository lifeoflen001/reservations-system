@props([
    'label',
    'value',
    'icon' => 'info',
    'tone' => 'info',
    'context' => null,
    'href' => null,
    'trend' => null,
    'loading' => false,
])

@if($loading)
    <div class="metric-card mini-kpi-card" aria-busy="true"><x-ui.skeleton class="mini-kpi-card__loading" /></div>
@elseif($href)
    <a class="metric-card mini-kpi-card mini-kpi-card--link" href="{{ $href }}" aria-label="{{ $label }}: {{ $value }}">
        <span class="mini-kpi-card__content"><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($trend)<small class="mini-kpi-card__trend">{{ $trend }}</small>@elseif($context)<small>{{ $context }}</small>@endif</span>
        <i class="metric-card__icon metric-card__icon--{{ $tone }}"><x-ui.icon :name="$icon" size="19" /></i>
    </a>
@else
    <article class="metric-card mini-kpi-card">
        <span class="mini-kpi-card__content"><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($trend)<small class="mini-kpi-card__trend">{{ $trend }}</small>@elseif($context)<small>{{ $context }}</small>@endif</span>
        <i class="metric-card__icon metric-card__icon--{{ $tone }}"><x-ui.icon :name="$icon" size="19" /></i>
    </article>
@endif
