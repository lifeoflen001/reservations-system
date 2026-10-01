<?php

namespace App\Mail;

use App\Models\ContactEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class ContactEnquiryNotification extends Mailable
{
    use Queueable;

    public function __construct(public readonly ContactEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lodgix website enquiry #'.$this->enquiry->getKey(),
            replyTo: [new Address($this->enquiry->email, preg_replace('/[\r\n]+/', ' ', $this->enquiry->name) ?? '')],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-enquiry');
    }
}
