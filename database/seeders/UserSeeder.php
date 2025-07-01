<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            ['name' => 'Luca',    'email' => 'luca@example.com'],
            ['name' => 'Adrien',  'email' => 'adrien@example.com'],
            ['name' => 'Antoine', 'email' => 'antoine@example.com'],
            ['name' => 'Brandon', 'email' => 'brandon@example.com'],
            ['name' => 'Thomas',  'email' => 'thomas@example.com'],
            ['name' => 'API',    'email' => 'api@example.com'],
        ];

        foreach ($users as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        }
    }
}
