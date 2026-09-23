<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['key' => 'announcement'],
            ['name' => 'Announcement', 'subject' => '{{ announcement_title }} · {{ property_name }}', 'body' => '{{ announcement_message }}<br><br>Read the full announcement: {{ action_url }}', 'channels' => json_encode(['email']), 'is_enabled' => true, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'announcement')->delete();
    }
};
