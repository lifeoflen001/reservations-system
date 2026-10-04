<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrganizationInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly OrganizationInvitation $invitation, public readonly string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to Lodgix');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.organization-invitation', with: [
            'invitation' => $this->invitation,
            'acceptUrl' => route('invitations.show', ['token' => $this->token]),
        ]);
    }
}
