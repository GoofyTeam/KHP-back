<?php

namespace Database\Factories;

use App\Enums\OrderStepStatus;
use App\Models\Order;
use App\Models\OrderStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStep>
 */
class OrderStepFactory extends Factory
{
    protected $model = OrderStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'position' => $this->faker->numberBetween(1, 5),
            'status' => OrderStepStatus::IN_PREP,
            'served_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => OrderStepStatus::READY,
        ]);
    }

    public function served(): static
    {
        return $this->state(fn () => [
            'status' => OrderStepStatus::SERVED,
            'served_at' => now(),
        ]);
    }
}
