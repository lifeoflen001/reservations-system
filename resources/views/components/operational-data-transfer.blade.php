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
            <form method="POST" action="{{ route('data-transfer.import', $resource) }}" enctype="multipart/form-data" class="data-transfer-toolbar__form" data-import-form>
                @csrf
                <label class="ui-button ui-button--secondary ui-button--small" data-tooltip="Import a CSV file">
                    <x-ui.icon name="upload" size="14" /> Import CSV
                    <input type="file" name="file" accept=".csv,text/csv" required class="data-transfer-toolbar__file" data-import-file />
                </label>
            </form>
            <x-ui.modal id="data-import-{{ $resource }}" title="Import {{ $transferMeta['label'] }}" size="medium" :dirtyGuard="false" :showClose="false">
                <div class="data-import-progress" data-import-progress>
                    <div class="data-import-progress__summary">
                        <div class="data-import-progress__spinner" data-import-spinner aria-hidden="true"></div>
                        <div>
                            <strong data-import-status>Preparing import…</strong>
                            <p data-import-detail>Select a CSV file to begin.</p>
                        </div>
                    </div>
                    <div class="data-import-progress__bar" role="progressbar" aria-label="Import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span data-import-progress-bar></span>
                    </div>
                    <ol class="data-import-steps" aria-label="Import steps">
                        <li data-import-step="selected">File selected</li>
                        <li data-import-step="uploading">Uploading file</li>
                        <li data-import-step="importing">Validating and importing rows</li>
                        <li data-import-step="complete">Complete</li>
                    </ol>
                    <div class="data-import-errors" data-import-errors hidden>
                        <strong>Rows needing attention</strong>
                        <ul data-import-error-list></ul>
                    </div>
                    <div class="modal-form-footer data-import-progress__footer">
                        <button type="button" class="ui-button ui-button--secondary" data-import-close disabled>Close</button>
                        <button type="button" class="ui-button ui-button--primary" data-import-reload hidden>Reload page</button>
                    </div>
                </div>
            </x-ui.modal>
        @endif
    </div>
@endif
