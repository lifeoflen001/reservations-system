<?php

namespace App\Console\Commands;

use App\Models\PlatformAdministrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformCreateAdmin extends Command
{
    protected $signature = 'platform:create-admin {--name=} {--email=} {--password=}';
    protected $description = 'Create a Lodgix Platform Administrator securely.';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $password = (string) ($this->option('password') ?: $this->secret('Password (minimum 12 characters)'));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $this->error('Name, email and a password of at least 12 characters are required.');
            return self::FAILURE;
        }
        if (PlatformAdministrator::where('email', $email)->exists()) {
            $this->error('A Platform Administrator with that email already exists.');
            return self::FAILURE;
        }

        PlatformAdministrator::create(['uuid' => (string) Str::uuid(), 'name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'platform_admin', 'permissions' => PlatformAdministrator::DEFAULT_PERMISSIONS, 'status' => PlatformAdministrator::ACTIVE]);
        $this->info('Platform Administrator created. The password was not displayed or logged.');
        return self::SUCCESS;
    }
}
