<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['notification_alert', 'Notification alert', '{{ notification_title }} · {{ property_name }}', '{{ notification_message }}', ['email']],
            ['staff_invitation', 'Staff invitation', 'You have been invited to {{ property_name }}', 'Hello {{ user_name }}, an administrator created a Lodgix staff account for you. Sign in with username {{ username }} and the password provided by your administrator. You will be asked to change your password after signing in. Sign in here: {{ login_url }}', ['email']],
        ] as [$key, $name, $subject, $body, $channels]) {
            DB::table('email_templates')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'subject' => $subject, 'body' => $body, 'channels' => json_encode($channels), 'is_enabled' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', ['notification_alert', 'staff_invitation'])->delete();
    }
};
