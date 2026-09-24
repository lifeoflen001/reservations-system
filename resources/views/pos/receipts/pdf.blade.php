@php
    $propertySettings = app(\App\Services\PropertySettingsService::class);
    $propertyName = $property?->name ?? $propertySettings->name();
    $address = $property?->address ? collect([$property->address, $property->city, $property->country])->filter()->implode(', ') : $propertySettings->address();
    $email = $property?->email ?? $propertySettings->email();
    $phone = $property?->phone ?? $propertySettings->phone();
    $reservation = $order->roomCharge?->reservation ?? $order->reservation;
    $logoPath = public_path('assets/branding/lodgix-mark.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1d2733; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; line-height: 1.4; }
        .receipt { width: 100%; }
        .header { display: table; width: 100%; border-bottom: 1px solid #d9e0e7; padding-bottom: 14px; }
        .brand, .receipt-number { display: table-cell; vertical-align: top; }
        .brand { width: 68%; }
        .brand img, .mark { display: inline-block; width: 42px; height: 42px; margin-right: 9px; vertical-align: middle; }
        .mark { background: #ef7d22; color: #fff; font-size: 20px; font-weight: bold; line-height: 42px; text-align: center; }
        .property { display: inline-block; vertical-align: middle; }
        .property strong { display: block; font-size: 15px; }
        .property span { display: block; color: #697687; font-size: 9px; }
        .receipt-number { text-align: right; }
        .receipt-number span { display: block; color: #697687; font-size: 9px; }
        .receipt-number strong { display: block; margin: 3px 0; font-size: 14px; }
        .header-note { border-bottom: 1px dashed #cbd4dd; padding: 10px 0; text-align: center; }
        .guest { border: 1px solid #9ec9ea; background: #eff8ff; margin: 12px 0; padding: 8px 10px; }
        .guest strong { display: block; }
        .meta { display: table; width: 100%; margin: 13px 0; }
        .meta-item { display: table-cell; width: 50%; }
        .label { display: block; color: #697687; font-size: 8px; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
        .value { display: block; margin-top: 2px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #bac5d0; color: #697687; font-size: 8px; letter-spacing: .04em; padding: 7px 4px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e4e8ed; padding: 8px 4px; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin: 12px 0 0 auto; width: 44%; }
        .totals div { display: table; width: 100%; padding: 3px 0; }
        .totals span, .totals strong { display: table-cell; }
        .totals strong { text-align: right; }
        .grand { border-top: 1px solid #aeb9c4; font-size: 13px; padding-top: 7px !important; }
        .payments { border-top: 1px solid #d9e0e7; margin-top: 14px; padding-top: 10px; }
        .payment-line { display: table; width: 100%; padding: 2px 0; }
        .payment-line span, .payment-line strong { display: table-cell; }
        .payment-line strong { text-align: right; }
        footer { border-top: 1px dashed #cbd4dd; color: #697687; margin-top: 22px; padding-top: 10px; text-align: center; }
        footer strong { color: #1d2733; }
    </style>
</head>
<body>
<main class="receipt">
    <header class="header">
        <div class="brand">
            @if($logoData)<img src="{{ $logoData }}" alt="">@else<span class="mark">{{ str($propertyName)->substr(0, 1)->upper() }}</span>@endif
            <div class="property"><strong>{{ $propertyName }}</strong><span>{{ $address !== '—' ? $address : '' }}</span><span>{{ collect([$phone, $email])->filter()->join(' · ') }}</span><span>{{ $order->outlet?->name }}@if($order->outlet?->location) · {{ $order->outlet->location }}@endif</span></div>
        </div>
        <div class="receipt-number"><span>Receipt number</span><strong>{{ $order->order_number }}</strong><span>{{ $order->completed_at?->format('m/d/Y, h:i A') }}</span></div>
    </header>
    @if($order->outlet?->receipt_header)<div class="header-note">{{ $order->outlet->receipt_header }}</div>@endif
    @if($reservation)<div class="guest"><strong>Charge to room</strong>{{ $reservation->client?->full_name ?? $order->client?->full_name ?? 'Guest' }} · Room {{ $reservation->room?->room_number ?? $order->room?->room_number ?? '—' }} · Reservation {{ $reservation->code }}</div>@endif
    <section class="meta">
        <div class="meta-item"><span class="label">Cashier</span><span class="value">{{ $order->cashier?->name ?? '—' }}</span></div>
        <div class="meta-item"><span class="label">Payment method</span><span class="value">{{ $order->payments->pluck('method')->map(fn($method) => ucwords(str_replace('_', ' ', $method)))->join(', ') ?: '—' }}</span></div>
    </section>
    <table>
        <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Total</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            @php($quantity = (float) $item->quantity)
            <tr><td>{{ $item->product_name_snapshot }}</td><td class="num">{{ floor($quantity) === $quantity ? number_format($quantity, 0) : rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') }}</td><td class="num">{{ $formatter->format($item->unit_price) }}</td><td class="num">{{ $formatter->format($item->total) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <section class="totals">
        <div><span>Subtotal</span><strong>{{ $formatter->format($order->subtotal) }}</strong></div>
        <div><span>Tax</span><strong>{{ $formatter->format($order->tax_total) }}</strong></div>
        <div><span>Discount</span><strong>{{ $formatter->format($order->discount_total) }}</strong></div>
        <div class="grand"><span>Total</span><strong>{{ $formatter->format($order->total) }}</strong></div>
    </section>
    <section class="payments"><strong>Payment</strong>@foreach($order->payments as $payment)<div class="payment-line"><span>{{ ucwords(str_replace('_', ' ', $payment->method)) }}@if($payment->reference) · {{ $payment->reference }}@endif</span><strong>{{ $formatter->format($payment->amount) }}</strong></div>@endforeach</section>
    <footer><strong>{{ $propertyName }}</strong><br>Thank you for choosing {{ $propertyName }}.</footer>
</main>
</body>
</html>
