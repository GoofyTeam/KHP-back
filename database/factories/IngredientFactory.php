<?php

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ingredients = [
            'Tomate',
            'Oignon',
            'Ail',
            'Carotte',
            'Pomme de terre',
            'Courgette',
            'Aubergine',
            'Poivron rouge',
            'Poivron vert',
            'Brocoli',
            'Chou-fleur',
            'Épinards',
            'Salade',
            'Concombre',
            'Radis',
            'Betterave',
            'Navet',
            'Poireau',
            'Céleri',
            'Persil',
            'Basilic',
            'Coriandre',
            'Thym',
            'Romarin',
            'Oregano',
            'Menthe',
            'Ciboulette',
            'Aneth',
            'Sauge',
            'Laurier',
            'Paprika',
            'Cumin',
            'Curry',
            'Gingembre',
            'Cannelle',
            'Muscade',
            'Clou de girofle',
            'Cardamome',
            'Anis',
            'Fenouil',
            'Riz',
            'Pâtes',
            'Quinoa',
            'Boulgour',
            'Avoine',
            'Orge',
            'Semoule',
            'Farine de blé',
            'Farine de riz',
            'Fecule de pomme de terre',
            'Levure',
            'Huile d\'olive',
            'Huile de tournesol',
            'Beurre',
            'Margarine',
            'Crème fraîche',
            'Lait',
            'Yaourt',
            'Fromage blanc',
            'Gruyère',
            'Parmesan',
            'Mozzarella',
            'Chèvre',
            'Roquefort',
            'Camembert',
            'Œufs',
            'Poulet',
            'Bœuf',
            'Porc',
            'Agneau',
            'Saumon',
            'Thon',
            'Crevettes',
            'Moules',
            'Cabillaud',
            'Sardines',
            'Haricots verts',
            'Petits pois',
            'Haricots rouges',
            'Lentilles',
            'Pois chiches',
            'Amandes',
            'Noix',
            'Noisettes',
            'Pignons',
            'Graines de tournesol',
            'Pomme',
            'Poire',
            'Banane',
            'Orange',
            'Citron',
            'Pamplemousse',
            'Kiwi',
            'Fraise',
            'Framboise',
            'Myrtille',
            'Cerise',
            'Abricot',
            'Pêche',
            'Prune',
            'Raisin',
            'Melon',
            'Pastèque',
            'Ananas',
            'Mangue',
            'Avocat',
            'Olives',
            'Sucre',
            'Miel',
            'Sirop d\'érable',
            'Chocolat noir',
            'Chocolat au lait',
            'Cacao en poudre',
            'Vanille',
            'Café',
            'Thé',
            'Vinaigre balsamique',
            'Vinaigre de vin',
            'Moutarde',
            'Ketchup',
            'Mayonnaise',
            'Sauce soja',
        ];

        return [
            'name' => $this->faker->randomElement($ingredients).' '.$this->faker->numberBetween(1, 999),
            'unit' => $this->faker->randomElement(MeasurementUnit::values()),
            'base_quantity' => $this->faker->numberBetween(1, 1000),
            'base_unit' => $this->faker->randomElement(MeasurementUnit::values()),
            'barcode' => $this->faker->unique()->ean13(),
            'company_id' => Company::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Ingredient $ingredient) {
            if (! array_key_exists('category_id', $ingredient->getAttributes())) {
                $companyId = $ingredient->company_id;
                if (isset($this->categoryCache[$companyId])) {
                    $ingredient->category_id = $this->categoryCache[$companyId];
                } else {
                    $categoryId = Category::where('company_id', $companyId)
                        ->value('id');
                    if ($categoryId === null) {
                        $categoryId = Category::factory()->create([
                            'company_id' => $companyId,
                        ])->id;
                    }
                    $this->categoryCache[$companyId] = $categoryId;
                    $ingredient->category_id = $categoryId;
                }
            }
        });
    }
}
