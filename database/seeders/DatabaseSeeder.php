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

        if (! User::query()->where('email', $email)->exists()) {
            // A random password is generated and printed once — no default credentials in source control.
            $password = Str::password(16);

            User::query()->create([
                'name' => 'مدير النظام',
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ])->assignRole('super-admin');

            $this->command?->warn("Super admin created: {$email} / {$password}  (change it after first login)");
        }
    }
}
