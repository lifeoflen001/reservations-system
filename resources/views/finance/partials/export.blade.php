@props(['report', 'label' => 'Export'])
@can('finance.reports.export')
<button type="button" class="ui-button ui-button--secondary" data-modal-open="finance-export-{{ $report }}"><x-ui.icon name="download" size="16" /> {{ $label }}</button>
<x-ui.modal id="finance-export-{{ $report }}" title="Export finance data" size="default">
    <form method="GET" action="{{ route('finance.exports', $report) }}">
        <div class="settings-form-grid">
            <x-form.select name="format" label="Format" required>
                <option value="csv">CSV</option>
                <option value="pdf">PDF</option>
                <option value="print">Print view</option>
            </x-form.select>
            <x-form.input name="from" type="date" label="From date" value="{{ request('from') }}" />
            <x-form.input name="to" type="date" label="To date" value="{{ request('to') }}" />
            @if($report === 'transactions')<x-form.input name="search" label="Search" value="{{ request('search') }}" placeholder="Transaction, reference or description" />@endif
            <x-form.select name="account_id" label="Account">
                <option value="">All accounts</option>
                @foreach(($accounts ?? collect()) as $account)
                    <option value="{{ $account->id }}" @selected((string) request('account_id') === (string) $account->id)>{{ $account->name }}</option>
                @endforeach
            </x-form.select>
            @if($report === 'transactions')
                <x-form.select name="type" label="Transaction type"><option value="all">All types</option>@foreach(($types ?? collect()) as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>@endforeach</x-form.select>
                <x-form.select name="direction" label="Direction"><option value="all">Money in and out</option><option value="credit" @selected(request('direction') === 'credit')>Money in</option><option value="debit" @selected(request('direction') === 'debit')>Money out</option></x-form.select>
            @endif
            @if(in_array($report, ['transactions', 'expenses', 'reconciliation'], true))
                <x-form.select name="status" label="Status"><option value="all">All statuses</option>@foreach(['posted','reversed','draft','pending_approval','approved','paid','rejected','completed','pending'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</x-form.select>
            @endif
        </div>
        <div class="modal-form-footer">
            <button type="button" class="ui-button ui-button--secondary" data-modal-close>Cancel</button>
            <button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="download" size="16" /> Export</button>
        </div>
    </form>
</x-ui.modal>
@endcan
