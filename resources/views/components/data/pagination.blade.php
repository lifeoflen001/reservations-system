@props(['paginator', 'default' => 15])

@php
    $currentPerPage = (int) $paginator->perPage();
    $query = request()->except(['page', 'per_page']);
@endphp

<div class="pagination-wrap">
    <div class="pagination-size-control">
        <span class="pagination-summary">
            Showing {{ $paginator->firstItem() ?: 0 }}–{{ $paginator->lastItem() ?: 0 }} of {{ $paginator->total() }}
        </span>
        <form method="GET" action="{{ request()->url() }}" class="pagination-size-form">
            @foreach($query as $name => $value)
                @if(is_array($value))
                    @foreach($value as $item)
                        <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                    @endforeach
                @elseif($value !== null)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="rows-per-page-{{ $paginator->getPageName() }}">Rows</label>
            <select id="rows-per-page-{{ $paginator->getPageName() }}" name="per_page" aria-label="Rows per page" onchange="this.form.submit()">
                @foreach(\App\Support\TablePagination::options() as $option)
                    <option value="{{ $option }}" @selected($currentPerPage === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <span>per page</span>
        </form>
    </div>

    @if($paginator->hasPages())
        {{ $paginator->onEachSide(1)->links('pagination.app') }}
    @endif
</div>
