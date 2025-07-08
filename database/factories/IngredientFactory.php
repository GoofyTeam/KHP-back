<?php

namespace Database\Factories;

use App\Enums\UnitEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Farine',
                'Sucre',
                'Sel',
                'Poivre',
                'Œufs',
                'Lait',
                'Beurre',
                'Huile d\'olive',
                'Tomate',
                'Oignon',
                'Ail',
                'Carotte',
                'Pomme de terre',
                'Poulet',
                'Bœuf',
                'Porc',
                'Saumon',
                'Thon',
                'Riz',
                'Pâtes',
                'Chocolat',
                'Vanille',
                'Cannelle',
                'Levure',
                'Yaourt',
                'Crème',
                'Fromage',
                'Moutarde',
                'Ketchup',
                'Mayonnaise',
                'Café',
                'Thé',
                'Vinaigre',
                'Citron',
                'Orange',
                'Pomme',
                'Banane',
            ]),
            'unit' => $this->faker->randomElement(UnitEnum::values()),
        ];
    }
}
