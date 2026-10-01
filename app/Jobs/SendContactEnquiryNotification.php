<?php

namespace App\Jobs;

use App\Models\ContactEnquiry;
use App\Mail\ContactEnquiryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendContactEnquiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $enquiryId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $recipient = config('hotel.contact.email');
        if (! is_string($recipient) || trim($recipient) === '') {
            return;
        }

        $enquiry = ContactEnquiry::query()->findOrFail($this->enquiryId);
        Mail::to($recipient)->send(new ContactEnquiryNotification($enquiry));
    }
}
