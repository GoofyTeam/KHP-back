<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Location;
use App\Models\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Location::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'name' => $this->faker->unique()->word.'-'.$counter, // Garantir l'unicité
            'company_id' => Company::factory(),
            'location_type_id' => LocationType::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
