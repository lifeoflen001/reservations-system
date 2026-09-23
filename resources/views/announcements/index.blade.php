@extends('layouts.app')
@section('content')
<x-page-header title="Announcements" subtitle="Create and manage announcements for your organization.">
    <a class="ui-button ui-button--secondary" href="{{ route('announcements.dashboard') }}"><x-ui.icon name="grid" size="16" /> Dashboard view</a>
    @can('create', \App\Models\Announcement::class)<a class="ui-button ui-button--primary" href="{{ route('announcements.index', ['new' => 1]) }}"><x-ui.icon name="plus" size="16" /> Add announcement</a>@endcan
</x-page-header>
<section class="announcement-filters ui-card">
    <form method="GET" action="{{ route('announcements.index') }}" class="filter-toolbar">
        <x-form.input name="search" value="{{ request('search') }}" placeholder="Search announcements..." aria-label="Search announcements" />
        <x-form.select name="category" aria-label="Filter by category"><option value="all">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category','all') === $category)>{{ $category }}</option>@endforeach</x-form.select>
        <x-form.select name="department_id" aria-label="Filter by department"><option value="all">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department_id','all') === (string)$department->id)>{{ $department->name }}</option>@endforeach</x-form.select>
        <x-form.select name="status" aria-label="Filter by status"><option value="all">All statuses</option>@foreach(['draft','scheduled','active','expired','archived'] as $status)<option value="{{ $status }}" @selected(request('status','all') === $status)>{{ ucfirst($status) }}</option>@endforeach</x-form.select>
        <x-form.input name="date_from" type="date" value="{{ request('date_from') }}" aria-label="Start date from" />
        <x-form.input name="date_to" type="date" value="{{ request('date_to') }}" aria-label="Start date to" />
        <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filters</button>
        <a class="ui-button ui-button--secondary" href="{{ route('announcements.index') }}">Reset</a>
    </form>
</section>
<section class="ui-card announcement-table-card">
    <x-data.table caption="Announcements" class="announcement-table"><thead><tr><th>#</th><th>Title</th><th>Category</th><th>Date range</th><th>Audience</th><th>Attachments</th><th>Status</th><th class="table-actions">Actions</th></tr></thead><tbody>
    @forelse($announcements as $announcement)
        <tr><td>{{ $announcements->firstItem() + $loop->index }}</td><td><a class="announcement-title" href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a><div class="announcement-badges">@if($announcement->is_featured)<x-ui.badge variant="brand">Featured</x-ui.badge>@endif @if($announcement->is_high_priority)<x-ui.badge variant="danger">High priority</x-ui.badge>@endif</div></td><td><x-ui.badge variant="neutral">{{ $announcement->category }}</x-ui.badge></td><td><div class="announcement-dates">{{ $announcement->start_at?->format('Y-m-d') }}@if($announcement->end_at)<span>to</span>{{ $announcement->end_at->format('Y-m-d') }}@endif</div></td><td><x-ui.badge variant="info">{{ $announcement->audienceLabel() }}</x-ui.badge></td><td>{{ $announcement->attachments->count() ?: '—' }}</td><td><x-ui.badge :variant="match($announcement->status) {'active' => 'success', 'scheduled' => 'info', 'expired' => 'warning', 'archived' => 'neutral', default => 'brand'}">{{ $announcement->statusLabel() }}</x-ui.badge></td><td class="table-actions"><div class="row-actions"><a class="icon-button" href="{{ route('announcements.show', $announcement) }}" aria-label="View announcement" data-tooltip="View"><x-ui.icon name="eye" size="17" /></a>@can('update',$announcement)<a class="icon-button" href="{{ route('announcements.edit',$announcement) }}" aria-label="Edit announcement" data-tooltip="Edit"><x-ui.icon name="edit" size="17" /></a>@endcan @can('statistics',$announcement)<a class="icon-button" href="{{ route('announcements.statistics',$announcement) }}" aria-label="View statistics" data-tooltip="Statistics"><x-ui.icon name="chart" size="17" /></a>@endcan @can('archive',$announcement) @if($announcement->status !== 'archived')<form method="POST" action="{{ route('announcements.archive',$announcement) }}" data-confirm="Archive this announcement? Its history will be preserved.">@csrf<button class="icon-button icon-button--danger" type="submit" aria-label="Archive announcement" data-tooltip="Archive"><x-ui.icon name="trash" size="17" /></button></form>@endif @endcan</div></td></tr>
    @empty <tr><td colspan="8"><div class="empty-state"><x-ui.icon name="bell" size="28" /><strong>No announcements found.</strong><span>Create one or clear the current filters.</span></div></td></tr>@endforelse
    </tbody></x-data.table>
    <x-data.pagination :paginator="$announcements" />
</section>

@if($openNew || $editAnnouncement)
@php($edit = $editAnnouncement)
<x-ui.modal id="announcement-form" :title="$edit ? 'Edit announcement' : 'Add new announcement'" size="large" open="true">
<form method="POST" action="{{ $edit ? route('announcements.update',$edit) : route('announcements.store') }}" enctype="multipart/form-data" data-draft-form data-draft-lifecycle="announcement" data-draft-key="announcement-{{ $edit ? 'edit-'.$edit->id : 'new' }}">@csrf @if($edit) @method('PUT') @endif
    <div class="announcement-form-grid">
        <x-form.input name="title" label="Title" :value="$edit?->title" placeholder="e.g. Annual company picnic 2026" required fieldClass="announcement-form-grid__full" />
        <x-form.select name="category" label="Category" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category',$edit?->category)===$category)>{{ $category }}</option>@endforeach</x-form.select>
        <x-form.input name="start_at" type="datetime-local" label="Start date" :value="$edit?->start_at?->format('Y-m-d\\TH:i')" required />
        <x-form.input name="end_at" type="datetime-local" label="End date" :value="$edit?->end_at?->format('Y-m-d\\TH:i')" />
        <x-form.textarea name="short_description" label="Short description" rows="2" :value="$edit?->short_description" required fieldClass="announcement-form-grid__full" />
        <div class="form-field announcement-form-grid__full"><label for="announcement-content">Content <span class="required-mark">*</span></label><div class="rich-toolbar" role="toolbar"><button type="button" data-rich-command="bold"><strong>B</strong></button><button type="button" data-rich-command="italic"><em>I</em></button><button type="button" data-rich-command="insertUnorderedList">• List</button><button type="button" data-rich-command="createLink">Link</button></div><textarea id="announcement-content" name="content" class="form-control announcement-content-editor" rows="8" required>{{ old('content',$edit?->content) }}</textarea><small class="form-help">Basic formatting is allowed; scripts and unsafe links are removed before saving.</small></div>
        <div class="announcement-audience announcement-form-grid__full"><span class="form-label">Audience</span><label class="checkbox-field"><input type="checkbox" name="is_company_wide" value="1" @checked(old('is_company_wide',$edit?->is_company_wide ?? true)) data-company-wide-toggle> Company-wide announcement</label><div class="announcement-department-picker" data-department-picker><span class="form-help">Or target selected departments:</span><div class="announcement-department-list">@foreach($departments as $department)<label class="checkbox-field"><input type="checkbox" name="department_ids[]" value="{{ $department->id }}" @checked($edit?->departments?->contains('id',$department->id))>{{ $department->name }}</label>@endforeach</div></div></div>
        <div class="announcement-flags"><label class="checkbox-field"><input type="checkbox" name="is_featured" value="1" @checked($edit?->is_featured)> Featured</label><label class="checkbox-field"><input type="checkbox" name="is_high_priority" value="1" @checked($edit?->is_high_priority)> High priority</label></div>
        <div class="form-field announcement-form-grid__full"><label for="announcement-attachments">Attachments</label><input id="announcement-attachments" name="attachments[]" type="file" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp"><small class="form-help">Up to 5 private files, 10 MB each.</small></div>
    </div>
    <div class="modal-form-footer"><span class="task-draft-status" data-draft-status role="status" aria-live="polite"></span><a class="ui-button ui-button--secondary" href="{{ route('announcements.index') }}">Cancel</a><button class="ui-button ui-button--secondary" name="publish_now" value="0" type="submit">Save draft</button><button class="ui-button ui-button--primary" name="publish_now" value="1" type="submit"><x-ui.icon name="send" size="16" /> Publish</button></div>
</form>
</x-ui.modal>
@endif
@endsection
