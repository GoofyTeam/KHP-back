<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuOrder;
use Illuminate\Database\Seeder;

class MenuOrderSeeder extends Seeder
{
    public function run(): void
    {
        Menu::all()->each(function (Menu $menu): void {
            MenuOrder::factory()->count(3)->create([
                'menu_id' => $menu->id,
            ]);
        });
    }
}
