@php
    $download = $download ?? false;
    $propertySettings = app(\App\Services\PropertySettingsService::class);
    $propertyName = $property?->name ?? $propertySettings->name();
    $address = $property?->address ? collect([$property->address, $property->city, $property->country])->filter()->implode(', ') : $propertySettings->address();
    $email = $property?->email ?? $propertySettings->email();
    $phone = $property?->phone ?? $propertySettings->phone();
    $reservation = $order->roomCharge?->reservation ?? $order->reservation;
    $logoPath = public_path('assets/branding/lodgix-mark.png');
@endphp

@if(!$download)
    @extends('layouts.app')
    @section('content')
    <x-page-header title="Receipt {{ $order->order_number }}" subtitle="POS receipt and reprint.">
        <a class="ui-button ui-button--secondary" href="{{ route('pos.receipts.download', $order) }}"><x-ui.icon name="download" size="16" /> Download PDF</a>
        <button class="ui-button ui-button--primary" type="button" onclick="window.print()"><x-ui.icon name="print" size="16" /> Print</button>
    </x-page-header>
@endif

<article class="pos-receipt {{ $download ? 'pos-receipt__download' : '' }}">
    <header>
        <div class="pos-receipt__brand">
            @if(is_file($logoPath))
                <img class="pos-receipt__logo" src="{{ asset('assets/branding/lodgix-mark.png') }}" alt="">
            @else
                <span class="pos-receipt__fallback-mark">{{ str($propertyName)->substr(0, 1)->upper() }}</span>
            @endif
            <div class="pos-receipt__property">
                <strong>{{ $propertyName }}</strong>
                @if($address && $address !== '—')<span>{{ $address }}</span>@endif
                @if($phone || $email)<span>{{ collect([$phone, $email])->filter()->join(' · ') }}</span>@endif
                <span>{{ $order->outlet?->name }}@if($order->outlet?->location) · {{ $order->outlet->location }}@endif</span>
            </div>
        </div>
        <div class="pos-receipt__number"><span>Receipt number</span><strong>{{ $order->order_number }}</strong><span>{{ $order->completed_at?->format('m/d/Y, h:i A') }}</span></div>
    </header>

    @if($order->outlet?->receipt_header)<p class="pos-receipt__header">{{ $order->outlet->receipt_header }}</p>@endif
    @if($reservation)
        <div class="pos-receipt__reservation"><strong>Charge to room</strong>{{ $reservation->client?->full_name ?? $order->client?->full_name ?? 'Guest' }} · Room {{ $reservation->room?->room_number ?? $order->room?->room_number ?? '—' }} · Reservation {{ $reservation->code }}</div>
    @endif

    <div class="pos-receipt__meta">
        <div><small>Cashier</small><strong>{{ $order->cashier?->name ?? '—' }}</strong></div>
        <div><small>Payment method</small><strong>{{ $order->payments->pluck('method')->map(fn($method) => ucwords(str_replace('_', ' ', $method)))->join(', ') ?: '—' }}</strong></div>
        @if(!$reservation)<div><small>Guest / room</small><strong>{{ $order->client?->full_name ?? 'Walk-in' }} @if($order->room) · {{ $order->room->room_number }}@endif</strong></div>@endif
    </div>

    <table>
        <thead><tr><th>Item</th><th class="pos-receipt__quantity">Qty</th><th class="pos-receipt__numeric">Unit price</th><th class="pos-receipt__numeric">Total</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            @php($quantity = (float) $item->quantity)
            <tr><td>{{ $item->product_name_snapshot }}</td><td class="pos-receipt__quantity">{{ floor($quantity) === $quantity ? number_format($quantity, 0) : rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') }}</td><td class="pos-receipt__numeric">{{ $formatter->format($item->unit_price) }}</td><td class="pos-receipt__numeric">{{ $formatter->format($item->total) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <div class="pos-receipt__totals">
        <span>Subtotal <strong>{{ $formatter->format($order->subtotal) }}</strong></span>
        <span>Tax <strong>{{ $formatter->format($order->tax_total) }}</strong></span>
        <span>Discount <strong>{{ $formatter->format($order->discount_total) }}</strong></span>
        <span class="pos-receipt__grand-total">Total <strong>{{ $formatter->format($order->total) }}</strong></span>
    </div>
    <div class="pos-receipt__payments">
        <strong>Payment</strong>
        @foreach($order->payments as $payment)<span class="pos-receipt__payment-line"><span>{{ ucwords(str_replace('_', ' ', $payment->method)) }} @if($payment->reference) · {{ $payment->reference }}@endif</span><strong>{{ $formatter->format($payment->amount) }}</strong></span>@endforeach
    </div>
    <footer><strong>{{ $propertyName }}</strong><div class="pos-receipt__footer-note">{{ $propertyName ? 'Thank you for choosing '.$propertyName.'.' : 'Thank you for your business.' }}</div></footer>
</article>

@if(!$download)
    @endsection
@endif
