<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\WebsiteAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactEnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $enquiries = ContactEnquiry::query()
            ->when($request->filled('status') && in_array($request->query('status'), ContactEnquiry::STATUSES, true), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('contact-enquiries.index', compact('enquiries'));
    }

    public function show(ContactEnquiry $contactEnquiry): View
    {
        return view('contact-enquiries.show', compact('contactEnquiry'));
    }

    public function updateStatus(Request $request, ContactEnquiry $contactEnquiry): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(ContactEnquiry::STATUSES)], 'internal_notes' => ['nullable', 'string', 'max:5000']]);
        $timestamps = match ($validated['status']) {
            'read' => ['read_at' => $contactEnquiry->read_at ?: now()],
            'replied' => ['read_at' => $contactEnquiry->read_at ?: now(), 'replied_at' => now()],
            'closed' => ['read_at' => $contactEnquiry->read_at ?: now(), 'closed_at' => now()],
            default => [],
        };
        $contactEnquiry->update(array_merge($validated, $timestamps));
        WebsiteAuditLog::create(['user_id' => $request->user()->id, 'action' => 'enquiry.status_changed', 'target_type' => ContactEnquiry::class, 'target_id' => $contactEnquiry->id, 'metadata' => ['status' => $validated['status']]]);

        $route = $request->routeIs('website.enquiries.*') ? 'website.enquiries.show' : 'contact-enquiries.show';
        return redirect()->route($route, $contactEnquiry)->with('success', 'Enquiry status updated.');
    }
}
