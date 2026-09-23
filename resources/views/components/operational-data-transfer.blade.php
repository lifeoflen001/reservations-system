@php
    $transferPermissions = [
        'rooms' => ['label' => 'Rooms', 'view' => 'rooms.view', 'import' => ['rooms.create', 'rooms.manage']],
        'clients' => ['label' => 'Clients', 'view' => 'clients.view', 'import' => ['clients.create', 'clients.manage']],
        'staff' => ['label' => 'Staff', 'view' => 'staff.view', 'import' => ['staff.create', 'staff.manage']],
        'tasks' => ['label' => 'Tasks', 'view' => 'tasks.view', 'import' => ['tasks.create', 'tasks.manage']],
        'maintenance' => ['label' => 'Maintenance', 'view' => 'maintenance.view', 'import' => ['maintenance.create', 'maintenance.manage']],
        'housekeeping' => ['label' => 'Housekeeping', 'view' => 'housekeeping.view', 'import' => ['housekeeping.create', 'housekeeping.manage']],
    ];
    $transferMeta = $transferPermissions[$resource] ?? null;
    $canTransferExport = $transferMeta && auth()->user()->hasPermission($transferMeta['view']);
    $canTransferImport = $transferMeta && collect($transferMeta['import'])->contains(fn ($permission) => auth()->user()->hasPermission($permission));
@endphp

@if($transferMeta && ($canTransferExport || $canTransferImport))
    <div class="data-transfer-toolbar" aria-label="{{ $transferMeta['label'] }} data transfer">
        @if($canTransferExport)
            <span class="data-transfer-toolbar__label">Export</span>
            <a class="ui-button ui-button--secondary ui-button--small" href="{{ route('data-transfer.export', [$resource, 'csv']) }}"><x-ui.icon name="download" size="14" /> CSV</a>
            <a class="ui-button ui-button--secondary ui-button--small" href="{{ route('data-transfer.export', [$resource, 'pdf']) }}"><x-ui.icon name="document" size="14" /> PDF</a>
        @endif
        @if($canTransferImport)
            <a class="ui-button ui-button--secondary ui-button--small" href="{{ route('data-transfer.template', $resource) }}"><x-ui.icon name="download" size="14" /> Template</a>
            <form method="POST" action="{{ route('data-transfer.import', $resource) }}" enctype="multipart/form-data" class="data-transfer-toolbar__form">
                @csrf
                <label class="ui-button ui-button--secondary ui-button--small" data-tooltip="Import a CSV file">
                    <x-ui.icon name="upload" size="14" /> Import CSV
                    <input type="file" name="file" accept=".csv,text/csv" required onchange="this.form.submit()" class="data-transfer-toolbar__file" />
                </label>
            </form>
        @endif
    </div>
@endif
