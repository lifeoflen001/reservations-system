@php
    $payment = $invoice->payment;
    $reservation = $payment?->reservation;
    $client = $payment?->client;
    $propertySettings = app(\App\Services\PropertySettingsService::class);
    $propertyName = $property?->name ?? $propertySettings->name();
    $address = $propertySettings->address();
    $email = $property?->email ?? ($propertySettings->email() ?: '—');
    $phone = $property?->phone ?? ($propertySettings->phone() ?: '—');
    $amount = (float) ($payment?->amount ?? 0);
@endphp
<article class="invoice-document">
    <header class="invoice-document__brand">
        <div class="invoice-document__identity"><span class="invoice-mark">H</span><div><h2>{{ $propertyName }}</h2><p>{{ config('hotel.brand.name') }} · Complete Hotel Management System</p></div></div>
        <div class="invoice-document__title"><strong>INVOICE</strong><span>{{ $invoice->invoice_number }}</span></div>
    </header>
    <div class="invoice-document__rule"></div>
    <div class="invoice-meta-grid">
        <div><small>Invoice number</small><strong>{{ $invoice->invoice_number }}</strong></div>
        <div><small>Issue date</small><strong>{{ $invoice->issue_date?->format('m/d/Y') ?? '—' }}</strong></div>
        <div><small>Status</small><x-ui.badge :variant="$payment?->status->badgeVariant() ?? 'neutral'">{{ $payment?->status->label() ?? '—' }}</x-ui.badge></div>
        <div><small>Method</small><strong>{{ $payment ? ucwords(str_replace('_', ' ', $payment->method)) : '—' }}</strong></div>
    </div>
    <div class="invoice-parties">
        <section><small>Issuer</small><h3>{{ $propertyName }}</h3><p>{{ $address }}<br>{{ $email }} · {{ $phone }}</p></section>
        <section><small>Customer</small><h3>{{ $client?->full_name ?? '—' }}</h3><p>{{ implode(', ', array_filter([$client?->city, $client?->country])) ?: '—' }}<br>{{ $client?->email ?? '—' }} · {{ $client?->phone ?? '—' }}</p></section>
    </div>
    <table class="invoice-lines"><thead><tr><th>Description</th><th>Quantity</th><th>Unit price</th><th>Total</th></tr></thead><tbody><tr><td><strong>Accommodation payment</strong><small>Reservation {{ $reservation?->code ?? '—' }} | Room {{ $reservation?->room?->room_number ?? '—' }} · {{ $reservation?->room?->roomType?->name ?? 'Room' }} | {{ $reservation?->check_in?->format('m/d/Y') ?? '—' }} - {{ $reservation?->check_out?->format('m/d/Y') ?? '—' }}</small></td><td>1</td><td>{{ $formatter->format($amount) }}</td><td>{{ $formatter->format($amount) }}</td></tr></tbody></table>
    <div class="invoice-total"><span>Subtotal <strong>{{ $formatter->format($amount) }}</strong></span><span>Total <strong>{{ $formatter->format($amount) }}</strong></span></div>
    <footer class="invoice-document__footer">Reference: {{ $payment?->reference ?? '—' }} · This document was generated electronically by {{ config('hotel.brand.name') }}.</footer>
</article>
