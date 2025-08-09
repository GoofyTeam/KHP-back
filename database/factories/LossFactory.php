<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Loss;
use Illuminate\Database\Eloquent\Factories\Factory;

class LossFactory extends Factory
{
    protected $model = Loss::class;

    public function definition()
    {
        $company = Company::inRandomOrder()->first();
        $location = Location::where('company_id', $company->id)->inRandomOrder()->first();
        $ingredient = Ingredient::where('company_id', $company->id)->inRandomOrder()->first();

        return [
            'company_id' => $company->id,
            'ingredient_type' => \App\Models\Ingredient::class,
            'ingredient_id' => $ingredient->id,
            'location_id' => $location->id,
            'quantity' => $this->faker->randomFloat(2, 0.1, 5),
            'unit' => 'portion',
            'reason' => 'casse',
            'comment' => $this->faker->sentence(),
        ];
    }
}
