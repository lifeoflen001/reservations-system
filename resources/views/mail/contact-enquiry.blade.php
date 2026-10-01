A new Lodgix website enquiry was received.

Name: {{ $enquiry->name }}
Hotel / company: {{ $enquiry->company ?: 'Not provided' }}
Email: {{ $enquiry->email }}
Phone: {{ $enquiry->phone ?: 'Not provided' }}
Country: {{ $enquiry->country ?: 'Not provided' }}
Number of rooms: {{ $enquiry->hotel_size ?: 'Not provided' }}
Enquiry type: {{ str($enquiry->enquiry_type)->replace('_', ' ')->title() }}

Message:
{{ $enquiry->message }}
