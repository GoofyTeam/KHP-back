<?php

namespace Database\Seeders;

use App\Models\SpecialQuickAccess;
use App\Models\User;
use Illuminate\Database\Seeder;

class SpecialQuickAccessSeeder extends Seeder
{
    public function run(): void
    {
        $default = [
            'name' => 'Move Quantity',
            'url' => '/movequantity',
        ];

        User::all()->each(function (User $user) use ($default) {
            SpecialQuickAccess::updateOrCreate(
                ['user_id' => $user->id],
                $default
            );
        });
    }
}
