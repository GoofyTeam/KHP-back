<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'table_id' => Table::factory()->for($company),
            'user_id' => User::factory()->for($company),
            'status' => OrderStatus::PENDING,
            'pending_at' => now(),
            'served_at' => null,
            'payed_at' => null,
            'canceled_at' => null,
        ];
    }

    public function served(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::SERVED,
            'served_at' => now(),
        ]);
    }

    public function payed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PAYED,
            'payed_at' => now(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::CANCELED,
            'canceled_at' => now(),
        ]);
    }
}
