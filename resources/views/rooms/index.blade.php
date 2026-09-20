@extends('layouts.app')

@php($formatter = app(\App\Support\CurrencyFormatter::class))
@php($tab = request('tab', 'map'))
@php($formRoom = $editRoom)
@php($canCategoryManage = auth()->user()?->hasPermission('room_categories.manage') || auth()->user()?->hasPermission('rooms.manage'))
@php($canTypeManage = auth()->user()?->hasPermission('room_types.manage') || auth()->user()?->hasPermission('rooms.manage'))
@php($canFloorManage = auth()->user()?->hasPermission('floors.manage') || auth()->user()?->hasPermission('rooms.manage'))
@php($canStatusManage = auth()->user()?->hasPermission('rooms.manage_status') || auth()->user()?->hasPermission('rooms.manage'))

@section('content')
<x-page-header title="Rooms" subtitle="Room inventory, categories, types and operational status.">
    @if($tab === 'catalog' && $canFloorManage)
        <button class="ui-button ui-button--secondary" data-modal-open="floor-modal"><x-ui.icon name="building" size="16" /> Manage floors</button>
    @endif
    @can('create', \App\Models\Room::class)
        <a class="ui-button ui-button--primary" href="{{ route('rooms.index', ['new' => 1, 'tab' => $tab]) }}"><x-ui.icon name="plus" size="16" /> New room</a>
    @endcan
</x-page-header>

@if ($errors->any())<x-feedback.alert type="danger" class="page-feedback">{{ $errors->first() }}</x-feedback.alert>@endif

<x-ui.tabs :tabs="[
    ['label' => 'Room map', 'href' => route('rooms.index', ['tab' => 'map']), 'active' => $tab === 'map'],
    ['label' => 'Room list', 'href' => route('rooms.index', ['tab' => 'list']), 'active' => $tab === 'list'],
    ['label' => 'Categories & types', 'href' => route('rooms.index', ['tab' => 'catalog']), 'active' => $tab === 'catalog'],
    ['label' => 'Statuses', 'href' => route('rooms.index', ['tab' => 'statuses']), 'active' => $tab === 'statuses'],
]" />

@if ($tab === 'map')
    @forelse ($mapRooms->groupBy('floor_id') as $floorId => $floorRooms)
        @php($floor = $floorRooms->first()->floor)
        <section class="room-floor">
            <div class="room-floor__heading"><h2>{{ $floor?->name ?? 'Unassigned floor' }}</h2><span>{{ $floorRooms->count() }} {{ Str::plural('Room', $floorRooms->count()) }}</span></div>
            <div class="room-map-grid">
                @foreach ($floorRooms as $room)
                    <a class="room-card room-card--{{ $room->operational_status->value }}" href="{{ route('rooms.show', $room) }}">
                        <div class="room-card__top"><strong>{{ $room->room_number }}</strong><span>{{ $room->category?->name ?? '—' }}</span></div>
                        <p>{{ $room->roomType?->name ?? 'Room' }} · {{ $formatter->format($room->base_rate) }}</p>
                        <b class="room-card__status">{{ $room->operational_status->label() }}</b>
                        <x-ui.badge :variant="$room->housekeeping_status === \App\Enums\HousekeepingStatus::Clean ? 'success' : 'warning'">{{ ucfirst($room->housekeeping_status->value) }}</x-ui.badge>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <section class="ui-card"><div class="empty-state"><x-ui.icon name="bed" size="28" /><strong>No active rooms yet.</strong><span>Create a floor, then add rooms to populate the room map.</span><a class="ui-button ui-button--primary" href="{{ route('rooms.index', ['tab' => 'catalog']) }}">Manage floors</a></div></section>
    @endforelse
@elseif ($tab === 'list')
    <form class="filter-toolbar reservation-filters" method="GET" action="{{ route('rooms.index') }}">
        <input type="hidden" name="tab" value="list">
        <x-form.input name="search" value="{{ request('search') }}" placeholder="Search rooms" aria-label="Search rooms" />
        <x-form.select name="status" aria-label="Filter by room status"><option value="all">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status', 'all') === $status->value)>{{ $status->label() }}</option>@endforeach</x-form.select>
        <x-form.select name="housekeeping" aria-label="Filter by housekeeping"><option value="all">All housekeeping</option>@foreach ($housekeepingStatuses as $status)<option value="{{ $status->value }}" @selected(request('housekeeping', 'all') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</x-form.select>
        <x-form.select name="floor_id" aria-label="Filter by floor"><option value="all">All floors</option>@foreach ($floors as $floor)<option value="{{ $floor->id }}" @selected((string) request('floor_id', 'all') === (string) $floor->id)>{{ $floor->name }}</option>@endforeach</x-form.select>
        <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filter</button><a class="ui-button ui-button--secondary" href="{{ route('rooms.index', ['tab' => 'list']) }}">Reset</a>
    </form>
    <section class="ui-card"><x-data.table caption="Room list"><thead><tr><th>Room</th><th>Floor</th><th>Category</th><th>Room type</th><th>Rate</th><th>Capacity</th><th>Operational status</th><th>Housekeeping</th><th>Actions</th></tr></thead><tbody>
        @forelse ($rooms as $room)
            <tr><td><strong>{{ $room->room_number }}</strong></td><td>{{ $room->floor?->name ?? '—' }}</td><td>{{ $room->category?->name ?? '—' }}</td><td>{{ $room->roomType?->name ?? '—' }}</td><td>{{ $formatter->format($room->base_rate) }}</td><td>{{ $room->capacity }}</td><td><x-ui.badge>{{ $room->operational_status->label() }}</x-ui.badge></td><td><x-ui.badge :variant="$room->housekeeping_status === \App\Enums\HousekeepingStatus::Clean ? 'success' : 'warning'">{{ ucfirst($room->housekeeping_status->value) }}</x-ui.badge></td><td><div class="row-actions"><a class="icon-button" href="{{ route('rooms.show', $room) }}" aria-label="View room" data-tooltip="View"><x-ui.icon name="eye" size="17" /></a>@can('update', $room)<a class="icon-button" href="{{ route('rooms.edit', $room) }}" aria-label="Edit room" data-tooltip="Edit"><x-ui.icon name="edit" size="17" /></a>@endcan @can('delete', $room)<form method="POST" action="{{ route('rooms.destroy', $room) }}" data-confirm="Archive this room?">@csrf @method('DELETE')<button class="icon-button icon-button--danger" type="submit" aria-label="Archive room" data-tooltip="Archive"><x-ui.icon name="trash" size="17" /></button></form>@endcan</div></td></tr>
        @empty
            <tr><td colspan="9"><div class="empty-state"><x-ui.icon name="bed" size="28" /><strong>No rooms found.</strong><span>Add a room to start managing inventory.</span></div></td></tr>
        @endforelse
    </tbody></x-data.table></section>
@elseif ($tab === 'catalog')
    <div class="catalog-grid catalog-grid--reference">
        <section class="ui-card catalog-panel">
            <header class="ui-card__header">
                <div class="ui-card__heading"><h2>Category</h2></div>
                @if($canCategoryManage)<button class="ui-button ui-button--info ui-button--compact" data-modal-open="category-modal"><x-ui.icon name="plus" size="14" /> New</button>@endif
            </header>
            <div class="catalog-table-wrap">
                <table class="catalog-table"><thead><tr><th>Name</th><th>Color</th><th>Actions</th></tr></thead><tbody>
                    @forelse($categories as $category)
                        <tr><td><strong>{{ $category->name }}</strong><small>{{ $category->description ?: 'No description' }}</small></td><td><span class="category-color" style="--category-color: {{ $category->color ?: '#69a7e8' }}" aria-label="Category color {{ $category->color ?: '#69a7e8' }}"></span></td><td>@if($canCategoryManage)<div class="row-actions"><a class="icon-button icon-button--compact" href="{{ route('rooms.index', ['tab' => 'catalog', 'edit_category' => $category->id]) }}" aria-label="Edit category" data-tooltip="Edit"><x-ui.icon name="edit" size="15" /></a><form method="POST" action="{{ route('rooms.categories.destroy', $category) }}" data-confirm="Delete this room category?">@csrf @method('DELETE')<button class="icon-button icon-button--danger icon-button--compact" type="submit" aria-label="Delete category" data-tooltip="Delete"><x-ui.icon name="trash" size="15" /></button></form></div>@else — @endif</td></tr>
                    @empty
                        <tr><td colspan="3"><div class="catalog-empty">No room categories yet.</div></td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>
        <section class="ui-card catalog-panel">
            <header class="ui-card__header">
                <div class="ui-card__heading"><h2>Room type</h2></div>
                @if($canTypeManage)<button class="ui-button ui-button--info ui-button--compact" data-modal-open="type-modal"><x-ui.icon name="plus" size="14" /> New</button>@endif
            </header>
            <div class="catalog-table-wrap">
                <table class="catalog-table"><thead><tr><th>Name</th><th>Capacity</th><th>Base rate</th><th>Actions</th></tr></thead><tbody>
                    @forelse($types as $type)
                        <tr><td><strong>{{ $type->name }}</strong><small>{{ $type->bed_type ?: 'Bed type not set' }}</small></td><td>{{ $type->capacity }}</td><td>{{ $formatter->format($type->base_rate) }}</td><td>@if($canTypeManage)<div class="row-actions"><a class="icon-button icon-button--compact" href="{{ route('rooms.index', ['tab' => 'catalog', 'edit_type' => $type->id]) }}" aria-label="Edit room type" data-tooltip="Edit"><x-ui.icon name="edit" size="15" /></a><form method="POST" action="{{ route('rooms.types.destroy', $type) }}" data-confirm="Delete this room type?">@csrf @method('DELETE')<button class="icon-button icon-button--danger icon-button--compact" type="submit" aria-label="Delete room type" data-tooltip="Delete"><x-ui.icon name="trash" size="15" /></button></form></div>@else — @endif</td></tr>
                    @empty
                        <tr><td colspan="4"><div class="catalog-empty">No room types yet.</div></td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>
    </div>
@else
    <section class="ui-card status-catalog-panel">
        <header class="ui-card__header">
            <div class="ui-card__heading"><h2>Statuses</h2></div>
            @if($canStatusManage)<a class="ui-button ui-button--info ui-button--compact" href="{{ route('rooms.index', ['tab' => 'statuses', 'new_status' => 1]) }}"><x-ui.icon name="plus" size="14" /> New</a>@endif
        </header>
        <div class="catalog-table-wrap">
            <table class="catalog-table status-catalog-table">
                <thead><tr><th>Code</th><th>Name</th><th>Color</th><th>Sellable</th><th>Order</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($statusDefinitions as $status)
                        <tr>
                            <td><code>{{ $status->code }}</code></td>
                            <td>{{ $status->name }}</td>
                            <td><span class="status-color-swatch" style="--status-color: {{ $status->color }}"></span><code>{{ $status->color }}</code></td>
                            <td>{{ $status->is_sellable ? 'Yes' : 'No' }}</td>
                            <td>{{ $status->sort_order }}</td>
                            <td>@if($canStatusManage)<div class="row-actions"><a class="icon-button icon-button--compact" href="{{ route('rooms.index', ['tab' => 'statuses', 'edit_status' => $status->id]) }}" aria-label="Edit {{ $status->name }}" data-tooltip="Edit"><x-ui.icon name="edit" size="15" /></a><form method="POST" action="{{ route('rooms.statuses.destroy', $status) }}" data-confirm="Delete the {{ $status->name }} room status? System statuses cannot be removed because room operations depend on them." data-confirm-title="Delete room status" data-confirm-label="Delete">@csrf @method('DELETE')<button class="icon-button icon-button--danger icon-button--compact" type="submit" aria-label="Delete {{ $status->name }}" data-tooltip="Delete"><x-ui.icon name="trash" size="15" /></button></form></div>@else — @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="catalog-empty">No room statuses configured.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if ($openStatusForm)
    <x-ui.modal id="status-form" :title="$editStatus ? 'Edit room status' : 'New room status'" :open="true">
        <form method="POST" action="{{ $editStatus ? route('rooms.statuses.update', $editStatus) : route('rooms.statuses.store') }}" data-draft-key="room-status-form">
            @csrf @if($editStatus) @method('PUT') @endif
            <div class="reservation-form__grid">
                <x-form.input name="code" label="Code" :value="$editStatus?->code" placeholder="available" required @readonly($editStatus?->is_system) help="Lowercase letters, numbers and underscores only." />
                <x-form.input name="name" label="Name" :value="$editStatus?->name" placeholder="Available" required />
                <x-form.input name="color" type="color" label="Color" :value="$editStatus?->color ?? '#42c55e'" required />
                <x-form.input name="sort_order" type="number" label="Display order" :value="$editStatus?->sort_order ?? 0" min="0" max="9999" required />
                <label class="check-field form-field--full"><input type="checkbox" name="is_sellable" value="1" @checked(old('is_sellable', $editStatus?->is_sellable ?? false))> Sellable</label>
            </div>
            @if($editStatus?->is_system)<p class="form-help">System status codes are required by room operations. Their name, color, sellable flag and display order can be changed.</p>@endif
            <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('rooms.index', ['tab' => 'statuses']) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div>
        </form>
    </x-ui.modal>
@endif

@if ($formRoom || $openNew)
    <x-ui.modal id="room-form" :title="$formRoom ? 'Edit room' : 'New room'" size="large" open="true">
        <form method="POST" action="{{ $formRoom ? route('rooms.update', $formRoom) : route('rooms.store') }}" class="reservation-form">
            @csrf @if($formRoom) @method('PUT') @endif
            <div class="reservation-form__grid">
                <x-form.input name="room_number" label="Room number" :value="$formRoom?->room_number" required />
                <x-form.input name="capacity" type="number" label="Capacity" :value="$formRoom?->capacity ?? 1" required />
                <x-form.select name="floor_id" label="Floor">
                    <option value="">No floor assigned</option>
                    @foreach($floors as $floor)<option value="{{ $floor->id }}" @selected((string) old('floor_id', $formRoom?->floor_id) === (string) $floor->id)>{{ $floor->name }}</option>@endforeach
                </x-form.select>
                <x-form.select name="room_category_id" label="Category" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('room_category_id', $formRoom?->room_category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
                <x-form.select name="room_type_id" label="Room type" required><option value="">Select room type</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string) old('room_type_id', $formRoom?->room_type_id) === (string) $type->id)>{{ $type->name }}</option>@endforeach</x-form.select>
                <x-form.select name="operational_status" label="Operational status" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('operational_status', $formRoom?->operational_status?->value ?? 'available') === $status->value)>{{ $status->label() }}</option>@endforeach</x-form.select>
                <x-form.select name="housekeeping_status" label="Housekeeping" required>@foreach($housekeepingStatuses as $status)<option value="{{ $status->value }}" @selected(old('housekeeping_status', $formRoom?->housekeeping_status?->value ?? 'clean') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</x-form.select>
                <x-form.input name="base_rate" type="number" step="0.01" label="Base rate" :value="$formRoom?->base_rate ?? 0" required />
                <x-form.textarea name="notes" label="Notes" class="form-field--full" rows="4" :value="$formRoom?->notes" />
                <label class="check-field form-field--full"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $formRoom?->is_active ?? true))> Active</label>
            </div>
            <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('rooms.index', ['tab' => $tab]) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div>
        </form>
    </x-ui.modal>
@endif

@if ($openRoom)
    <x-ui.modal id="room-details" title="Room details" size="large" open="true">
        <div class="room-detail-head"><div><span class="text-link">Room {{ $openRoom->room_number }}</span><h2>{{ $openRoom->roomType?->name ?? 'Room' }}</h2><p>{{ $openRoom->floor?->name ?? 'Unassigned floor' }} · {{ $openRoom->category?->name }}</p></div><x-ui.badge>{{ $openRoom->operational_status->label() }}</x-ui.badge></div>
        <div class="room-detail-grid"><div><small>Base rate</small><strong>{{ $formatter->format($openRoom->base_rate) }}</strong></div><div><small>Capacity</small><strong>{{ $openRoom->capacity }} guests</strong></div><div><small>Housekeeping</small><strong>{{ ucfirst($openRoom->housekeeping_status->value) }}</strong></div><div><small>Active</small><strong>{{ $openRoom->is_active ? 'Yes' : 'No' }}</strong></div></div>
        <p class="room-notes">{{ $openRoom->notes ?: 'No room notes.' }}</p>
        @can('manageStatus',$openRoom)<form class="room-status-form" method="POST" action="{{ route('rooms.status',$openRoom) }}">@csrf<x-form.select name="operational_status" label="Manual operational status">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($openRoom->operational_status === $status)>{{ $status->label() }}</option>@endforeach</x-form.select><button class="ui-button ui-button--secondary" type="submit">Update status</button></form>@endcan
        <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('rooms.edit', $openRoom) }}">Edit room</a><a class="ui-button ui-button--primary" href="{{ route('rooms.index', ['tab' => $tab]) }}">Close</a></div>
    </x-ui.modal>
@endif

<x-ui.modal id="category-modal" title="New room category"><form method="POST" action="{{ route('rooms.categories.store') }}">@csrf<x-form.input name="name" label="Name" required /><x-form.input name="color" type="color" label="Color" value="#69a7e8" required /><x-form.textarea name="description" label="Description" rows="3" /><div class="modal-form-footer"><button type="button" class="ui-button ui-button--secondary" data-modal-close>Cancel</button><button class="ui-button ui-button--primary" type="submit">Save</button></div></form></x-ui.modal>
<x-ui.modal id="type-modal" title="New room type"><form method="POST" action="{{ route('rooms.types.store') }}">@csrf<x-form.input name="name" label="Name" required /><div class="reservation-form__grid"><x-form.input name="capacity" type="number" label="Capacity" value="1" required /><x-form.input name="bed_type" label="Bed type" placeholder="King bed" /><x-form.input name="base_rate" type="number" step="0.01" label="Base rate" value="0" required /></div><x-form.textarea name="description" label="Description" rows="3" /><div class="modal-form-footer"><button type="button" class="ui-button ui-button--secondary" data-modal-close>Cancel</button><button class="ui-button ui-button--primary" type="submit">Save</button></div></form></x-ui.modal>
@if($editCategory)
    <x-ui.modal id="category-edit-modal" title="Edit room category" :open="true">
        <form method="POST" action="{{ route('rooms.categories.update', $editCategory) }}">
            @csrf @method('PUT')
            <x-form.input name="name" label="Name" :value="$editCategory->name" required />
            <x-form.input name="color" type="color" label="Color" :value="$editCategory->color ?: '#69a7e8'" required />
            <x-form.textarea name="description" label="Description" rows="3" :value="$editCategory->description" />
            <input type="hidden" name="is_active" value="0"><label class="check-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editCategory->is_active))> Active</label>
            <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('rooms.index', ['tab' => 'catalog']) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div>
        </form>
    </x-ui.modal>
@endif
@if($editType)
    <x-ui.modal id="type-edit-modal" title="Edit room type" :open="true">
        <form method="POST" action="{{ route('rooms.types.update', $editType) }}">
            @csrf @method('PUT')
            <x-form.input name="name" label="Name" :value="$editType->name" required />
            <div class="reservation-form__grid"><x-form.input name="capacity" type="number" label="Capacity" :value="$editType->capacity" required /><x-form.input name="bed_type" label="Bed type" :value="$editType->bed_type" placeholder="King bed" /><x-form.input name="base_rate" type="number" step="0.01" label="Base rate" :value="$editType->base_rate" required /></div>
            <x-form.textarea name="description" label="Description" rows="3" :value="$editType->description" />
            <input type="hidden" name="is_active" value="0"><label class="check-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editType->is_active))> Active</label>
            <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('rooms.index', ['tab' => 'catalog']) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div>
        </form>
    </x-ui.modal>
@endif
<x-ui.modal id="floor-modal" :title="$editFloor ? 'Edit floor' : 'Manage floors'" size="large" :open="(bool) $editFloor">
    <div class="floor-manager">
        <div class="floor-manager__list"><h3>Floors</h3>
            @forelse($allFloors as $floor)
                <div class="catalog-row"><div><strong>{{ $floor->name }}</strong><small>{{ $floor->rooms_count }} {{ Str::plural('room', $floor->rooms_count) }} · {{ $floor->is_active ? 'Active' : 'Inactive' }}</small></div><div class="row-actions"><a class="icon-button icon-button--compact" href="{{ route('rooms.index', ['tab' => 'catalog', 'edit_floor' => $floor->id]) }}" aria-label="Edit floor" data-tooltip="Edit"><x-ui.icon name="edit" size="15" /></a><form method="POST" action="{{ route('rooms.floors.destroy', $floor) }}" data-confirm="Delete this floor?">@csrf @method('DELETE')<button class="icon-button icon-button--danger icon-button--compact" type="submit" aria-label="Delete floor" data-tooltip="Delete"><x-ui.icon name="trash" size="15" /></button></form></div></div>
            @empty
                <div class="catalog-empty">No floors yet.</div>
            @endforelse
        </div>
        <form method="POST" action="{{ $editFloor ? route('rooms.floors.update', $editFloor) : route('rooms.floors.store') }}">
            @csrf @if($editFloor) @method('PUT') @endif
            <x-form.input name="name" label="Floor name" :value="$editFloor?->name" placeholder="Floor 1" required />
            <x-form.input name="sort_order" type="number" label="Display order" :value="$editFloor?->sort_order ?? 0" min="0" />
            @if($editFloor)<input type="hidden" name="is_active" value="0"><label class="check-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editFloor->is_active))> Active</label>@endif
            <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('rooms.index', ['tab' => 'catalog']) }}">Close</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> {{ $editFloor ? 'Save changes' : 'Add floor' }}</button></div>
        </form>
    </div>
</x-ui.modal>
@endsection
