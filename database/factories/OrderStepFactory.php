<?php

namespace Database\Factories;

use App\Enums\OrderStepStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OrderStep>
 */
class OrderStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => $this->faker->numberBetween(1, 5),
            'status' => $this->faker->randomElement(OrderStepStatus::values()),
            'served_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
