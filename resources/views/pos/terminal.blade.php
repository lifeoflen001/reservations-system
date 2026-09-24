@extends('layouts.app')

@section('content')
<x-page-header title="POS Terminal" subtitle="Fast hotel sales for every outlet, guest and room charge.">
    <a class="ui-button ui-button--secondary" href="{{ route('pos.orders') }}"><x-ui.icon name="document" size="16" /> Sales</a>
    <a class="ui-button ui-button--secondary" href="{{ route('pos.shifts') }}"><x-ui.icon name="clock" size="16" /> Shifts</a>
</x-page-header>

@if ($errors->any())<x-feedback.alert type="danger" class="page-feedback">{{ $errors->first() }}</x-feedback.alert>@endif
<div class="pos-feedback" data-pos-feedback hidden role="alert"></div>

<form class="pos-terminal" method="POST" action="{{ route('pos.checkout') }}" data-pos-terminal data-currency-symbol="{{ app(\App\Services\PropertySettingsService::class)->currency()->symbol ?? '' }}" data-product-search-url="{{ route('pos.products.search') }}" data-guest-search-url="{{ route('pos.guests.search') }}" data-receipt-base-url="{{ url('/pos/receipts') }}">
    @csrf
    <div class="pos-terminal__toolbar">
        <x-form.select name="outlet_id" label="Outlet" id="pos-outlet" data-pos-outlet>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((int) $outletId === $outlet->id)>{{ $outlet->name }}</option>@endforeach</x-form.select>
        <div class="pos-terminal__shift" data-pos-shift-state><span>Shift</span>@if($openShift)<x-ui.badge variant="success">Open</x-ui.badge><small>{{ $openShift->opened_at?->format('m/d/Y, h:i A') }}</small><input type="hidden" name="shift_id" value="{{ $openShift->id }}" data-pos-shift>@elseif(config('hotel.pos.require_shift'))<x-ui.badge variant="warning">Required</x-ui.badge><small>Open a shift before checkout.</small><a class="text-link" href="{{ route('pos.shifts') }}">Open shift</a>@else<x-ui.badge variant="neutral">Optional</x-ui.badge><small>Sales can be completed without a shift.</small>@endif</div>
        <div class="pos-terminal__cashier"><span>Cashier</span><strong>{{ auth()->user()->name }}</strong></div>
    </div>

    <div class="pos-terminal__workspace">
        <section class="pos-catalog-panel" aria-label="Products">
            <div class="pos-search-row"><x-form.input name="search" label="Search products" placeholder="Name or SKU" aria-label="Search products" field-class="pos-search-field" :show-error="false" /><span class="pos-search-hint">Search is limited to active products in this outlet.</span></div>
            <div class="pos-category-row" data-pos-categories><button type="button" class="pos-category is-active" data-category="all">All <span>{{ $products->count() }}</span></button>@foreach($categories as $category)<button type="button" class="pos-category" data-category="{{ $category->id }}">{{ $category->name }} <span>{{ $category->products_count }}</span></button>@endforeach</div>
            <div class="pos-product-grid" data-pos-products>
                @forelse($products as $product)
                    <button type="button" class="pos-product-card" data-pos-product data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->selling_price }}" data-category-id="{{ $product->category_id }}" data-category="{{ $product->category?->name }}" data-tax-rate="{{ $product->tax_rate }}" data-tax-inclusive="{{ $product->tax_inclusive ? '1' : '0' }}" data-stock="{{ $product->track_stock ? $product->stock_quantity : '' }}" data-track-stock="{{ $product->track_stock ? '1' : '0' }}">
                        <span class="pos-product-card__icon"><x-ui.icon name="cube" size="18" /></span><span class="pos-product-card__name">{{ $product->name }}</span><small>{{ $product->category?->name ?? 'General' }} @if($product->sku) · {{ $product->sku }} @endif</small><strong>{{ $formatter->format($product->selling_price) }}</strong>@if($product->track_stock)<span class="pos-product-card__stock {{ $product->stock_quantity <= $product->reorder_level ? 'is-low' : '' }}">{{ number_format((float) $product->stock_quantity, 0) }} in stock</span>@endif
                    </button>
                @empty
                    <div class="empty-state"><x-ui.icon name="card" size="28" /><strong>No active POS products.</strong><span>Add products before opening the terminal.</span><a class="ui-button ui-button--secondary" href="{{ route('pos.products', ['new' => 1]) }}">Add product</a></div>
                @endforelse
            </div>
        </section>

        <aside class="pos-order-panel" aria-label="Current order">
            <div class="pos-order-panel__heading"><div><span class="pos-eyebrow">Current order</span><h2 data-pos-order-count>0 items</h2></div><button type="button" class="text-button" data-pos-clear>Clear</button></div>
            <div class="pos-cart" data-pos-cart><div class="pos-cart__empty"><x-ui.icon name="card" size="28" /><strong>Your order is empty</strong><span>Select products to begin a sale.</span></div></div>
            <div class="pos-order-panel__body">
                <div class="pos-guest-field"><label for="pos-guest-search">Guest / room <small>optional</small></label><input id="pos-guest-search" class="form-control" type="search" placeholder="Search room, guest or reservation" autocomplete="off" data-pos-guest-search><div class="pos-guest-results" data-pos-guest-results hidden></div><div class="pos-selected-guest" data-pos-selected-guest hidden><strong data-pos-selected-guest-name></strong><span data-pos-selected-guest-detail></span><button type="button" class="text-button" data-pos-clear-guest>Change</button></div></div>
                <input type="hidden" name="reservation_id" data-pos-reservation><input type="hidden" name="client_id" data-pos-client><input type="hidden" name="room_id" data-pos-room><input type="hidden" name="idempotency_key" data-pos-idempotency>
                @if($canDiscount)<div class="pos-discount-row"><x-form.select name="discount_type" label="Discount"><option value="fixed">Fixed amount</option><option value="percentage">Percentage</option></x-form.select><x-form.input name="discount_value" label="Value" type="number" step="0.01" min="0" value="0" data-pos-discount /></div>@endif
                <div class="pos-totals"><div><span>Subtotal</span><strong data-pos-subtotal>{{ $formatter->format(0) }}</strong></div><div><span>Tax</span><strong data-pos-tax>{{ $formatter->format(0) }}</strong></div>@if($canDiscount)<div><span>Discount</span><strong data-pos-discount-total>{{ $formatter->format(0) }}</strong></div>@endif<div class="pos-total"><span>Total</span><strong data-pos-total>{{ $formatter->format(0) }}</strong></div></div>
                <div class="pos-payment-section"><div class="pos-payment-section__heading"><strong>Payment method</strong><small data-pos-payment-help>Select one method</small></div><div class="pos-payment-methods" data-pos-payment-methods>@foreach($paymentMethods as $method)@php($paymentIcon = match($method->code) { 'cash' => 'currency', 'card' => 'card', 'mobile_money' => 'phone', 'bank_transfer' => 'building', 'charge_to_room' => 'bed', default => 'card' })<label class="pos-payment-method"><input type="radio" name="payment_method_choice" value="{{ $method->code }}" @checked($loop->first) data-pos-payment><span class="pos-payment-method__icon"><x-ui.icon name="{{ $paymentIcon }}" size="15" /></span><span>{{ $method->name }}</span></label>@endforeach</div><div class="pos-payment-reference" data-pos-payment-reference hidden><x-form.input name="payment_reference" label="Reference" placeholder="Receipt or transaction reference" :show-error="false" /></div></div>
                <button class="ui-button ui-button--primary pos-complete-button" type="submit" data-pos-complete @disabled(config('hotel.pos.require_shift') && ! $openShift)><x-ui.icon name="check" size="17" /> Complete sale</button>
            </div>
        </aside>
    </div>
</form>
@endsection
