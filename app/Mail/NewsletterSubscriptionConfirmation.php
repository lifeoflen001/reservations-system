<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsletterSubscriptionConfirmation extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly NewsletterSubscriber $subscriber,
        public readonly string $confirmationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your Lodgix newsletter subscription');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.newsletter.confirm');
    }
}
