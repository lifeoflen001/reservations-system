<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Department;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\PaymentMethod;
use App\Models\ReservationSource;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $usd = Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true,
        ]);

        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
        foreach ([
            ['name' => 'Management', 'description' => 'Property leadership and administration', 'sort_order' => 1],
            ['name' => 'Front Office', 'description' => 'Reception and guest services', 'sort_order' => 2],
            ['name' => 'Housekeeping', 'description' => 'Room preparation and cleaning', 'sort_order' => 3],
            ['name' => 'Maintenance', 'description' => 'Repairs and facilities', 'sort_order' => 4],
            ['name' => 'Finance', 'description' => 'Payments and financial controls', 'sort_order' => 5],
        ] as $department) {
            Department::firstOrCreate(['name' => $department['name']], $department + ['is_active' => true]);
        }

        // Keep legacy records recoverable for populated installations, but do
        // not continue showing an unused default department after reseeding.
        Department::query()->where('name', 'Administration')->whereDoesntHave('users')->whereDoesntHave('tasks')->update(['is_active' => false]);

        foreach ([['Direct', 'direct'], ['Booking.com', 'booking-com'], ['Expedia', 'expedia'], ['Walk-in', 'walk-in']] as $index => [$name, $code]) {
            ReservationSource::firstOrCreate(['name' => $name], ['code' => $code, 'sort_order' => $index + 1, 'is_active' => true]);
        }

        foreach ([
            ['code' => 'cash', 'name' => 'Cash', 'sort_order' => 1],
            ['code' => 'card', 'name' => 'Card', 'sort_order' => 2],
            ['code' => 'mobile_money', 'name' => 'Mobile money', 'sort_order' => 3],
            ['code' => 'bank_transfer', 'name' => 'Bank transfer', 'sort_order' => 4],
            ['code' => 'charge_to_room', 'name' => 'Charge to room', 'sort_order' => 5],
        ] as $method) {
            PaymentMethod::firstOrCreate(['code' => $method['code']], $method + ['is_active' => true]);
        }

        foreach ([
            ['reservation_confirmation', 'Reservation confirmation', 'Reservation {{ reservation_code }} confirmed', 'Hello {{ guest_name }}, your reservation {{ reservation_code }} at {{ property_name }} is confirmed.', ['in_app', 'email']],
            ['reservation_update', 'Reservation updated', 'Reservation {{ reservation_code }} updated', 'Hello {{ guest_name }}, your reservation {{ reservation_code }} has been updated.', ['in_app', 'email']],
            ['reservation_cancellation', 'Reservation cancellation', 'Reservation {{ reservation_code }} cancelled', 'Hello {{ guest_name }}, reservation {{ reservation_code }} has been cancelled.', ['in_app', 'email']],
            ['pre_arrival_reminder', 'Pre-arrival reminder', 'Your stay at {{ property_name }}', 'Hello {{ guest_name }}, we look forward to welcoming you for reservation {{ reservation_code }}.', ['email']],
            ['payment_receipt', 'Payment receipt', 'Payment received for {{ reservation_code }}', 'We received {{ paid_amount }} for reservation {{ reservation_code }}. Balance: {{ balance }}.', ['in_app', 'email']],
            ['invoice', 'Invoice', 'Invoice for {{ reservation_code }}', 'Your invoice for reservation {{ reservation_code }} at {{ property_name }} is ready.', ['email']],
            ['post_stay_thank_you', 'Post-stay thank you', 'Thank you for staying with {{ property_name }}', 'Thank you for staying with us, {{ guest_name }}. We hope to welcome you again.', ['email']],
            ['email_change_verification', 'Email change verification', 'Verify your HotelDesk email change', 'Hello {{ user_name }}, your HotelDesk email verification code is {{ verification_code }}. It expires in {{ expires_in }}. If you did not request this change, you can safely ignore this message.', ['email']],
            ['notification_alert', 'Notification alert', '{{ notification_title }} · {{ property_name }}', '{{ notification_message }}', ['email']],
            ['staff_invitation', 'Staff invitation', 'You have been invited to {{ property_name }}', 'Hello {{ user_name }}, an administrator created a Lodgix staff account for you. Sign in with username {{ username }} and the password provided by your administrator. You will be asked to change your password after signing in. Sign in here: {{ login_url }}', ['email']],
        ] as [$key, $name, $subject, $body, $channels]) {
            EmailTemplate::firstOrCreate(['key' => $key], compact('name', 'subject', 'body', 'channels') + ['is_enabled' => true]);
        }
    }
}
