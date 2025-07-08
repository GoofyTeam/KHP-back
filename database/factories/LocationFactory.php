<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locationTypes = [
            'Chambre froide',
            'Réserve',
            'Placard',
            'Étagère',
            'Congélateur',
            'Cuisine',
            'Bar',
            'Cave',
            'Garde-manger',
            'Salle de préparation',
        ];

        return [
            'name' => $this->faker->randomElement($locationTypes).' '.$this->faker->randomLetter(),
            'company_id' => Company::factory(),
        ];
    }
}
