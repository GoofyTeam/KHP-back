<?php

namespace Database\Factories;

use App\Enums\OrderHistoryAction;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderHistory>
 */
class OrderHistoryFactory extends Factory
{
    protected $model = OrderHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_step_id' => null,
            'step_menu_id' => null,
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'action' => OrderHistoryAction::ORDER_CREATED->value,
            'payload' => [],
            'reason' => null,
        ];
    }
}
