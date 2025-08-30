<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Perishable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Perishable>
 */
class PerishableFactory extends Factory
{
    protected $model = Perishable::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'quantity' => $this->faker->randomFloat(2, 0.1, 20),
            'company_id' => Company::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Perishable $perishable) {
            $companyId = $perishable->company_id ?? Company::factory()->create()->id;
            $perishable->company_id = $companyId;

            if (! $perishable->ingredient_id) {
                $perishable->ingredient_id = Ingredient::factory()->create([
                    'company_id' => $companyId,
                ])->id;
            }

            if (! $perishable->location_id) {
                $perishable->location_id = Location::factory()->create([
                    'company_id' => $companyId,
                ])->id;
            }
        });
    }
}
