<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Department;
use App\Models\EmailTemplate;
use App\Models\Installation;
use App\Models\Language;
use App\Models\PaymentMethod;
use App\Models\ReservationSource;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $usd = Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true,
        ]);

        Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
        foreach ([
            ['name' => 'Management', 'description' => 'Property leadership and administration', 'sort_order' => 1],
            ['name' => 'Front Office', 'description' => 'Reception and guest services', 'sort_order' => 2],
            ['name' => 'Housekeeping', 'description' => 'Room preparation and cleaning', 'sort_order' => 3],
            ['name' => 'Maintenance', 'description' => 'Repairs and facilities', 'sort_order' => 4],
            ['name' => 'Finance', 'description' => 'Payments and financial controls', 'sort_order' => 5],
            ['name' => 'Administration', 'description' => 'System administration', 'sort_order' => 6],
        ] as $department) {
            Department::updateOrCreate(['name' => $department['name']], $department + ['is_active' => true]);
        }

        foreach ([['Direct', 'direct'], ['Booking.com', 'booking-com'], ['Expedia', 'expedia'], ['Walk-in', 'walk-in']] as $index => [$name, $code]) {
            ReservationSource::updateOrCreate(['name' => $name], ['code' => $code, 'sort_order' => $index + 1, 'is_active' => true]);
        }

        foreach ([
            ['code' => 'cash', 'name' => 'Cash', 'sort_order' => 1],
            ['code' => 'card', 'name' => 'Card', 'sort_order' => 2],
            ['code' => 'bank_transfer', 'name' => 'Bank transfer', 'sort_order' => 3],
        ] as $method) {
            PaymentMethod::updateOrCreate(['code' => $method['code']], $method + ['is_active' => true]);
        }

        Installation::firstOrCreate([], [
            'status' => 'unconfigured',
            'base_currency_id' => $usd->getKey(),
        ]);

        foreach ([
            ['reservation_confirmation', 'Reservation confirmation', 'Reservation {{ reservation_code }} confirmed', 'Hello {{ guest_name }}, your reservation {{ reservation_code }} at {{ property_name }} is confirmed.', ['in_app', 'email']],
            ['reservation_update', 'Reservation updated', 'Reservation {{ reservation_code }} updated', 'Hello {{ guest_name }}, your reservation {{ reservation_code }} has been updated.', ['in_app', 'email']],
            ['reservation_cancellation', 'Reservation cancellation', 'Reservation {{ reservation_code }} cancelled', 'Hello {{ guest_name }}, reservation {{ reservation_code }} has been cancelled.', ['in_app', 'email']],
            ['pre_arrival_reminder', 'Pre-arrival reminder', 'Your stay at {{ property_name }}', 'Hello {{ guest_name }}, we look forward to welcoming you for reservation {{ reservation_code }}.', ['email']],
            ['payment_receipt', 'Payment receipt', 'Payment received for {{ reservation_code }}', 'We received {{ paid_amount }} for reservation {{ reservation_code }}. Balance: {{ balance }}.', ['in_app', 'email']],
            ['invoice', 'Invoice', 'Invoice for {{ reservation_code }}', 'Your invoice for reservation {{ reservation_code }} at {{ property_name }} is ready.', ['email']],
            ['post_stay_thank_you', 'Post-stay thank you', 'Thank you for staying with {{ property_name }}', 'Thank you for staying with us, {{ guest_name }}. We hope to welcome you again.', ['email']],
            ['email_change_verification', 'Email change verification', 'Verify your HotelDesk email change', 'Hello {{ user_name }}, your HotelDesk email verification code is {{ verification_code }}. It expires in {{ expires_in }}. If you did not request this change, you can safely ignore this message.', ['email']],
        ] as [$key, $name, $subject, $body, $channels]) {
            EmailTemplate::updateOrCreate(['key' => $key], compact('name', 'subject', 'body', 'channels') + ['is_enabled' => true]);
        }
    }
}
