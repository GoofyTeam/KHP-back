<?php

namespace Database\Factories;

use App\Enums\StepMenuStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StepMenu>
 */
class StepMenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity' => $this->faker->numberBetween(1, 10),
            'status' => $this->faker->randomElement(StepMenuStatus::values()),
            'note' => $this->faker->optional()->sentence(),
            'served_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
