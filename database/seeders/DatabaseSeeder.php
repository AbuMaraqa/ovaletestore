<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $email = (string) env('ADMIN_EMAIL', 'admin@ovalete.test');

        // Local/dev: fixed easy password (ADMIN_PASSWORD or 123456789).
        // Production: ADMIN_PASSWORD must be set, otherwise a random one is generated — never a weak default.
        $password = (string) (env('ADMIN_PASSWORD') ?: (app()->isProduction() ? Str::password(16) : '123456789'));

        // updateOrCreate: re-running the seeder also resets the existing admin's password.
        // The model's `hashed` cast hashes the plain password.
        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => 'مدير النظام', 'password' => $password, 'is_active' => true, 'email_verified_at' => now()],
        )->assignRole('super-admin');

        $this->command?->warn("Super admin: {$email} / {$password}");
    }
}
