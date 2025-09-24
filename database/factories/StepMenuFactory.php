<?php

namespace Database\Factories;

use App\Enums\StepMenuStatus;
use App\Models\Company;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\StepMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StepMenu>
 */
class StepMenuFactory extends Factory
{
    protected $model = StepMenu::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'order_step_id' => OrderStep::factory()->for(
                Order::factory()->for($company),
                'order'
            ),
            'menu_id' => Menu::factory()->for($company),
            'quantity' => $this->faker->numberBetween(1, 10),
            'status' => StepMenuStatus::IN_PREP,
            'note' => null,
            'served_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => StepMenuStatus::READY,
        ]);
    }

    public function served(): static
    {
        return $this->state(fn () => [
            'status' => StepMenuStatus::SERVED,
            'served_at' => now(),
        ]);
    }
}
