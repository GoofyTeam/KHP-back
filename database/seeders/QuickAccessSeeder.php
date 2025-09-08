<?php

namespace Database\Seeders;

use App\Models\QuickAccess;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuickAccessSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'index' => 1,
                'name' => 'Add to stock',
                'icon' => 'Plus',
                'icon_color' => 1, // primary
                'url' => '/stock/add',
            ],
            [
                'index' => 2,
                'name' => 'Menu Card',
                'icon' => 'Notebook',
                'icon_color' => 4, // info
                'url' => '/menucard',
            ],
            [
                'index' => 3,
                'name' => 'Stock',
                'icon' => 'Check',
                'icon_color' => 1, // primary
                'url' => '/stock',
            ],
            [
                'index' => 4,
                'name' => 'Take Order',
                'icon' => 'Notebook',
                'icon_color' => 1, // primary
                'url' => '/takeorder',
            ],
        ];

        User::all()->each(function (User $user) use ($defaults) {
            foreach ($defaults as $row) {
                QuickAccess::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'index' => $row['index'],
                    ],
                    [
                        'name' => $row['name'],
                        'icon' => $row['icon'],
                        'icon_color' => $row['icon_color'],
                        'url' => $row['url'],
                    ]
                );
            }
        });
    }
}
