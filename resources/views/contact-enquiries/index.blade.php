@extends('layouts.app')
@section('content')
@php($websiteContext = request()->routeIs('website.enquiries*'))
@php($enquiryIndexRoute = $websiteContext ? 'website.enquiries' : 'contact-enquiries.index')
@php($enquiryShowRoute = $websiteContext ? 'website.enquiries.show' : 'contact-enquiries.show')
<x-page-header title="Website enquiries" subtitle="Review messages sent through the Lodgix public contact form." />
@if($websiteContext) @include('website.partials.nav') @endif
<section class="ui-card">
    <form method="GET" action="{{ route($enquiryIndexRoute) }}" class="filter-toolbar">
        <label class="sr-only" for="enquiry-status">Filter by status</label>
        <select id="enquiry-status" name="status" class="form-control">
            <option value="">All statuses</option>
            @foreach(\App\Models\ContactEnquiry::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach
        </select>
        <button class="ui-button ui-button--secondary" type="submit">Filter</button>
    </form>
    <x-data.table caption="Website enquiries">
        <thead><tr><th>Received</th><th>Name / hotel</th><th>Email</th><th>Enquiry type</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($enquiries as $enquiry)
                <tr>
                    <td>{{ $enquiry->created_at?->format('Y-m-d H:i') }}</td>
                    <td><strong>{{ $enquiry->name }}</strong><br><span class="table-muted">{{ $enquiry->company ?: 'Hotel / company not provided' }}</span></td>
                    <td><a class="text-link" href="mailto:{{ $enquiry->email }}">{{ $enquiry->email }}</a></td>
                    <td>{{ str($enquiry->enquiry_type)->replace('_', ' ')->title() }}</td>
                    <td><x-ui.badge :variant="match($enquiry->status) {'new' => 'warning', 'replied' => 'success', 'closed' => 'neutral', default => 'info'}">{{ ucfirst($enquiry->status) }}</x-ui.badge></td>
                    <td><a class="ui-button ui-button--secondary" href="{{ route($enquiryShowRoute, $enquiry) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>No website enquiries.</strong><span>New public contact form submissions will appear here.</span></div></td></tr>
            @endforelse
        </tbody>
    </x-data.table>
    <x-data.pagination :paginator="$enquiries" />
</section>
@if($websiteContext) @include('website.partials.close') @endif
@endsection
