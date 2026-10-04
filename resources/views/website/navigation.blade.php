@extends('layouts.app')
@section('content')
<x-page-header title="Website navigation" subtitle="Manage public labels and visibility while protected routes remain controlled by code." />
@include('website.partials.nav')
@php($navigationRows = $items->flatMap(fn ($item) => collect([$item])->concat($item->children))->values())
<section class="ui-card website-navigation-card"><header class="ui-card__header"><div><h2>Public navigation</h2><p>Labels and visibility are editable; destinations remain application-controlled.</p></div></header><form method="POST" action="{{ route('website.navigation.update') }}">@csrf @method('PATCH')<div class="website-navigation-table"><x-data.table caption="Public navigation"><thead><tr><th>Order</th><th>Label</th><th>Destination</th><th>Visible</th></tr></thead><tbody>@foreach($navigationRows as $item)<tr><td>{{ $item->position }}</td><td class="{{ $item->parent_id ? 'website-navigation-child' : '' }}"><input class="form-control" name="items[{{ $loop->index }}][label]" value="{{ $item->label }}"><input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}"></td><td><code>{{ $item->destination }}</code></td><td><x-form.toggle name="items[{{ $loop->index }}][is_visible]" label="Visible" :checked="$item->is_visible" /></td></tr>@endforeach</tbody></x-data.table></div><div class="modal-form-footer"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="15" /> Save navigation</button></div></form></section>
@include('website.partials.close')
@endsection
