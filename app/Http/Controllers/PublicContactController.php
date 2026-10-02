<?php

namespace App\Http\Controllers;

use App\Jobs\SendContactEnquiryNotification;
use App\Models\ContactEnquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PublicContactController extends Controller
{
    public function create(): View
    {
        return view('public.contact', ['contactEmail' => config('hotel.contact.email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Treat filled honeypots as successful submissions without storing or
        // notifying; this avoids teaching simple bots how the filter works.
        if ($request->filled('website')) {
            return redirect()->route('public.contact')->with('success', 'Thanks. Your enquiry has been received.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
            'hotel_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'enquiry_type' => ['required', Rule::in(['general', 'pricing', 'implementation', 'integrations', 'support'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $enquiry = ContactEnquiry::query()->create($validated + ['status' => 'new']);

        if (filled(config('hotel.contact.email'))) {
            try {
                SendContactEnquiryNotification::dispatch($enquiry->getKey());
            } catch (Throwable $exception) {
                // The database record remains the source of truth if queue
                // dispatch is temporarily unavailable.
                Log::warning('Contact enquiry notification could not be queued.', [
                    'enquiry_id' => $enquiry->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        } else {
            Log::warning('Contact enquiry notification is not configured.', ['enquiry_id' => $enquiry->getKey()]);
        }

        return redirect()->route('public.contact')->with('success', 'Thanks. Your enquiry has been received.');
    }
}
