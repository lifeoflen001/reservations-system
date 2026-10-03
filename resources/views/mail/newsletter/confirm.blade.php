@component('mail::message')
# Confirm your Lodgix subscription

You asked to receive occasional product and hotel operations updates from Lodgix. Confirm your email address to activate your subscription.

@component('mail::button', ['url' => $confirmationUrl])
Confirm subscription
@endcomponent

If you did not request this, you can safely ignore this message.

Thanks,<br>
{{ config('hotel.brand.product_name', 'Lodgix') }}
@endcomponent
