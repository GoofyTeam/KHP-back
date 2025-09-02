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
 * @extends Factory<\App\Models\Loss>
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
        return [
            'loss_item_id' => Ingredient::factory(),
            'loss_item_type' => Ingredient::class,
            'location_id' => Location::factory(),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'quantity' => fake()->randomFloat(2, 0.1, 5),
            'reason' => fake()->sentence(),
        ];
    }

    /**
     * Crée une perte pour une préparation.
     */
    public function preparation(): static
    {
        return $this->state(fn () => [
            'loss_item_id' => Preparation::factory(),
            'loss_item_type' => Preparation::class,
        ]);
    }
}
