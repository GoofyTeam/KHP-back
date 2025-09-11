<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\QuickAccess;
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
                'icon_color' => 'primary',
                'url_key' => 'add_to_stock',
            ],
            [
                'index' => 2,
                'name' => 'Menu Card',
                'icon' => 'Notebook',
                'icon_color' => 'info',
                'url_key' => 'menu_card',
            ],
            [
                'index' => 3,
                'name' => 'Stock',
                'icon' => 'Check',
                'icon_color' => 'primary',
                'url_key' => 'stock',
            ],
            [
                'index' => 4,
                'name' => 'Take Order',
                'icon' => 'Notebook',
                'icon_color' => 'primary',
                'url_key' => 'take_order',
            ],
            [
                'index' => 5,
                'name' => 'Move Quantity',
                'icon' => 'NoIcon',
                'icon_color' => 'info',
                'url_key' => 'move_quantity',
            ],
        ];

        Company::all()->each(function (Company $company) use ($defaults) {
            foreach ($defaults as $row) {
                QuickAccess::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'index' => $row['index'],
                    ],
                    [
                        'name' => $row['name'],
                        'icon' => $row['icon'],
                        'icon_color' => $row['icon_color'],
                        'url_key' => $row['url_key'],
                    ]
                );
            }
        });
    }
}
