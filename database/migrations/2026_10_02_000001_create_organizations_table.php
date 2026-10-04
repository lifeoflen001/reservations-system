<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('country', 100)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('default_currency', 10)->nullable();
            $table->string('billing_email', 254)->nullable();
            $table->string('subscription_status', 24)->default('active')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
