<p>Hello,</p>
<p>{{ $invitation->inviter?->name ?: 'A Lodgix organization administrator' }} invited you to join <strong>{{ $invitation->organization->name }}</strong> on Lodgix.</p>
<p>Your assigned role is {{ $invitation->role?->label ?: 'team member' }}. This invitation expires {{ $invitation->expires_at?->toDayDateTimeString() }}.</p>
<p><a href="{{ $acceptUrl }}">Accept invitation</a></p>
<p>If you were not expecting this invitation, you can ignore this email.</p>
