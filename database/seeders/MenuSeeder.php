<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\Preparation;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Exemple de création d’un menu Pizza
        $menu = Menu::create([
            'name' => 'Pizza Margherita',
        ]);

        // Récupérer quelques ingrédients au hasard
        $ingredients = Ingredient::inRandomOrder()->take(2)->get();
        $preparation = Preparation::inRandomOrder()->first();

        // Attacher ingrédients
        foreach ($ingredients as $ingredient) {
            $menu->items()->create([
                'entity_type' => Ingredient::class,
                'entity_id' => $ingredient->id,
                'quantity' => rand(50, 200), // grammes
                'unit' => 'gram',
            ]);
        }

        // Attacher une préparation
        if ($preparation) {
            $menu->items()->create([
                'entity_type' => Preparation::class,
                'entity_id' => $preparation->id,
                'quantity' => 1,
                'unit' => 'piece',
            ]);
        }

        // Un deuxième menu pour varier
        $menu2 = Menu::create([
            'name' => 'Salade composée',
        ]);

        $ingredients2 = Ingredient::inRandomOrder()->take(3)->get();

        foreach ($ingredients2 as $ingredient) {
            $menu2->items()->create([
                'entity_type' => Ingredient::class,
                'entity_id' => $ingredient->id,
                'quantity' => rand(20, 100),
                'unit' => 'gram',
            ]);
        }
    }
}
