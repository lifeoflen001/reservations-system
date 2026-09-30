@php
    $proofItems = [
        ['icon' => 'calendar', 'label' => 'Reservations & Front Desk'],
        ['icon' => 'bed', 'label' => 'Room Operations'],
        ['icon' => 'card', 'label' => 'POS & Guest Charges'],
        ['icon' => 'currency', 'label' => 'Finance & Payments'],
        ['icon' => 'chart', 'label' => 'Reporting & Control'],
    ];
@endphp

<div class="public-proof-strip" aria-label="Lodgix capabilities">
    @foreach($proofItems as $item)
        <span class="public-proof-strip__item">
            <x-ui.icon :name="$item['icon']" size="16" />
            <span>{{ $item['label'] }}</span>
        </span>
    @endforeach
</div>
