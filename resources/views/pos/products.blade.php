@extends('layouts.app')

@section('content')
@php($currency = app(\App\Services\PropertySettingsService::class)->currency())
<x-page-header title="POS Products" subtitle="Manage fast-selling products, prices and optional stock tracking.">
    <a class="ui-button ui-button--secondary" href="{{ route('pos.catalog') }}"><x-ui.icon name="settings" size="16" /> Categories & outlets</a>
    @can('create', \App\Models\PosProduct::class)<a class="ui-button ui-button--primary" href="{{ route('pos.products', ['new' => 1]) }}"><x-ui.icon name="plus" size="16" /> New product</a>@endcan
</x-page-header>

<form class="filter-toolbar" method="GET" action="{{ route('pos.products') }}">
    <x-form.input name="search" value="{{ request('search') }}" placeholder="Search name or SKU" aria-label="Search products" />
    <x-form.select name="category_id" aria-label="Filter category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
    <x-form.select name="outlet_id" aria-label="Filter outlet"><option value="">All outlets</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((string) request('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>@endforeach</x-form.select>
    <x-form.select name="status" aria-label="Filter status"><option value="all">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></x-form.select>
    <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filter</button>
</form>

<section class="ui-card">
    <x-data.table caption="POS products">
        <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Outlet</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong><small class="table-muted">{{ $product->description }}</small></td>
                <td>{{ $product->sku ?: '—' }}</td>
                <td>{{ $product->category?->name ?? 'General' }}</td>
                <td>{{ $product->outlet?->name ?? 'All outlets' }}</td>
                <td>{{ $formatter->format($product->selling_price) }}</td>
                <td>@if($product->track_stock)<span class="{{ $product->stock_quantity <= $product->reorder_level ? 'text-danger' : '' }}">{{ number_format((float) $product->stock_quantity, 0) }}</span>@else<small>Not tracked</small>@endif</td>
                <td><x-ui.badge :variant="$product->is_active ? 'success' : 'neutral'">{{ $product->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                <td><div class="row-actions">@can('update', $product)<a class="icon-button" href="{{ route('pos.products', ['edit' => $product->id]) }}" aria-label="Edit product" data-tooltip="Edit"><x-ui.icon name="edit" size="17" /></a>@endcan @can('delete', $product)<form method="POST" action="{{ route('pos.products.destroy', $product) }}" data-confirm="Archive this POS product?">@csrf @method('DELETE')<button class="icon-button icon-button--danger" type="submit" aria-label="Archive product" data-tooltip="Archive"><x-ui.icon name="trash" size="17" /></button></form>@endcan</div></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><strong>No POS products found.</strong><span>Create the first product for the terminal.</span></div></td></tr>
        @endforelse
        </tbody>
    </x-data.table>
    <x-data.pagination :paginator="$products" />
</section>

@if($openNew || $editProduct)
<x-ui.modal id="pos-product-form" title="{{ $editProduct ? 'Edit POS product' : 'New POS product' }}" size="medium" open="true">
    <form method="POST" action="{{ $editProduct ? route('pos.products.update', $editProduct) : route('pos.products.store') }}" class="pos-compact-form" data-submit-lock>
        @csrf @if($editProduct) @method('PUT') @endif
        <div class="settings-form-grid">
            <x-form.input name="name" label="Name" :value="old('name', $editProduct?->name)" required />
            <x-form.input name="sku" label="SKU / code" :value="old('sku', $editProduct?->sku)" />
            <x-form.select name="category_id" label="Category"><option value="">General</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $editProduct?->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
            <x-form.select name="outlet_id" label="Outlet"><option value="">All outlets</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $editProduct?->outlet_id) === (string) $outlet->id)>{{ $outlet->name }}</option>@endforeach</x-form.select>
            <x-form.input name="selling_price" label="Selling price ({{ $currency->code }})" type="number" min="0" step="0.01" :value="old('selling_price', $editProduct?->selling_price)" required />
            <x-form.input name="tax_rate" label="Tax rate (%)" type="number" min="0" max="100" step="0.001" :value="old('tax_rate', $editProduct?->tax_rate ?? config('hotel.pos.default_tax_rate'))" />
            <x-form.input name="cost_price" label="Cost price ({{ $currency->code }})" type="number" min="0" step="0.01" :value="old('cost_price', $editProduct?->cost_price)" />
            <x-form.input name="stock_quantity" label="Current quantity" type="number" min="0" step="0.001" :value="old('stock_quantity', $editProduct?->stock_quantity ?? 0)" />
            <x-form.input name="reorder_level" label="Reorder level" type="number" min="0" step="0.001" :value="old('reorder_level', $editProduct?->reorder_level ?? 0)" />
            <x-form.toggle name="track_stock" label="Track stock" help="Prevent overselling" :checked="$editProduct?->track_stock ?? false" />
            <x-form.toggle name="is_active" label="Active" help="Available in POS" :checked="$editProduct?->is_active ?? true" />
            <x-form.toggle name="tax_inclusive" label="Tax-inclusive price" help="Price already includes tax" :checked="$editProduct?->tax_inclusive ?? false" />
            <x-form.textarea name="description" label="Description" rows="2" field-class="form-field--full">{{ old('description', $editProduct?->description) }}</x-form.textarea>
        </div>
        <div class="modal-form-footer">
            <button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save product</button>
        </div>
    </form>
</x-ui.modal>
@endif
@endsection
