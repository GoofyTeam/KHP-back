<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LocationType::class;

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
            'name' => $this->faker->unique()->word . '-' . $counter, // Utilisation d'un compteur pour garantir l'unicité
            'company_id' => Company::factory(),
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the location type is a default one.
     *
     * @return Factory
     */
    public function default(): Factory
    {
        static $defaultCounter = 0;
        $defaultTypes = ['Congélateur', 'Réfrigérateur', 'Réserve', 'Cave', 'Autre'];
        $defaultCounter = ($defaultCounter % count($defaultTypes));

        return $this->state(function (array $attributes) use (&$defaultCounter, $defaultTypes) {
            return [
                'name' => $defaultTypes[$defaultCounter++] . '-' . uniqid(),
                'is_default' => true,
            ];
        });
    }
}
