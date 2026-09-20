<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->index(['email', 'expires_at']);
        });

        DB::table('email_templates')->updateOrInsert(
            ['key' => 'email_change_verification'],
            [
                'name' => 'Email change verification',
                'subject' => 'Verify your HotelDesk email change',
                'body' => 'Hello {{ user_name }}, your HotelDesk email verification code is {{ verification_code }}. It expires in {{ expires_in }}. If you did not request this change, you can safely ignore this message.',
                'channels' => json_encode(['email']),
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_verifications');
        DB::table('email_templates')->where('key', 'email_change_verification')->delete();
    }
};
