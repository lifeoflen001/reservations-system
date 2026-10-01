<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
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
        $validated = $request->validate(['status' => ['required', Rule::in(ContactEnquiry::STATUSES)]]);
        $contactEnquiry->update($validated);

        return redirect()->route('contact-enquiries.show', $contactEnquiry)->with('success', 'Enquiry status updated.');
    }
}
