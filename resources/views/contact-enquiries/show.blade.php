@extends('layouts.app')
@section('content')
<x-page-header title="Website enquiry" subtitle="Received {{ $contactEnquiry->created_at?->format('Y-m-d H:i') }}">
    <a class="ui-button ui-button--secondary" href="{{ route('contact-enquiries.index') }}">Back to enquiries</a>
</x-page-header>
@if(session('success'))<x-feedback.alert type="success">{{ session('success') }}</x-feedback.alert>@endif
<section class="ui-card">
    <dl class="settings-summary-grid">
        <div><dt>Name</dt><dd>{{ $contactEnquiry->name }}</dd></div>
        <div><dt>Hotel / company</dt><dd>{{ $contactEnquiry->company ?: 'Not provided' }}</dd></div>
        <div><dt>Email</dt><dd><a class="text-link" href="mailto:{{ $contactEnquiry->email }}">{{ $contactEnquiry->email }}</a></dd></div>
        <div><dt>Phone</dt><dd>{{ $contactEnquiry->phone ?: 'Not provided' }}</dd></div>
        <div><dt>Country</dt><dd>{{ $contactEnquiry->country ?: 'Not provided' }}</dd></div>
        <div><dt>Number of rooms</dt><dd>{{ $contactEnquiry->hotel_size ?: 'Not provided' }}</dd></div>
        <div><dt>Enquiry type</dt><dd>{{ str($contactEnquiry->enquiry_type)->replace('_', ' ')->title() }}</dd></div>
        <div><dt>Status</dt><dd>{{ ucfirst($contactEnquiry->status) }}</dd></div>
    </dl>
    <div class="public-enquiry-message"><h2>Message</h2><p>{{ $contactEnquiry->message }}</p></div>
    @can('contact_enquiries.manage')
        <form method="POST" action="{{ route('contact-enquiries.status', $contactEnquiry) }}" class="settings-form-grid">
            @csrf @method('PATCH')
            <x-form.select name="status" label="Update status" required>
                @foreach(\App\Models\ContactEnquiry::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $contactEnquiry->status) === $status)>{{ ucfirst($status) }}</option>@endforeach
            </x-form.select>
            <button class="ui-button ui-button--primary" type="submit">Save status</button>
        </form>
    @endcan
</section>
@endsection
