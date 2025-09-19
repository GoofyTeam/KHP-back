<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Loss;
use App\Models\Preparation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loss>
 */
class LossFactory extends Factory
{
    protected $model = Loss::class;

    /**
     * Définition de l'état par défaut du modèle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'loss_item_id' => Ingredient::factory()->for($company),
            'loss_item_type' => Ingredient::class,
            'location_id' => Location::factory()->for($company),
            'company_id' => $company,
            'user_id' => User::factory()->for($company),
            'quantity' => fake()->randomFloat(2, 0.1, 5),
            'reason' => fake()->sentence(),
        ];
    }

    /**
     * Crée une perte pour une préparation.
     */
    public function preparation(): static
    {
        return $this->state(function (array $attributes) {
            $companyAttribute = $attributes['company_id'] ?? Company::factory();

            $preparationFactory = $companyAttribute instanceof Company || $companyAttribute instanceof Factory
                ? Preparation::factory()->for($companyAttribute)
                : Preparation::factory()->state([
                    'company_id' => $companyAttribute,
                ]);

            return [
                'loss_item_id' => $preparationFactory,
                'loss_item_type' => Preparation::class,
            ];
        });
    }
}
