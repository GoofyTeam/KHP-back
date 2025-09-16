<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Models\Order;
use App\Models\OrderStep;
use Illuminate\Database\Seeder;

class OrderStepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::query()->withCount('steps')->get();

        foreach ($orders as $order) {
            if ($order->steps_count > 0) {
                continue;
            }

            $progress = $this->progressForStatus($order->status);
            $steps = collect($this->stepsBlueprint());

            $steps->each(function (array $blueprint, int $index) use ($order, $progress): void {
                $status = $this->statusForProgress(min($progress, $blueprint['target']));

                OrderStep::query()->create([
                    'order_id' => $order->id,
                    'name' => $blueprint['name'],
                    'position' => $index + 1,
                    'status' => $status,
                    'served_at' => $status === OrderStepStatus::SERVED
                        ? now()->subMinutes(random_int(5, 60))
                        : null,
                ]);
            });
        }
    }

    /**
     * @return array<int, array{name: string, target: int}>
     */
    private function stepsBlueprint(): array
    {
        return [
            ['name' => 'Préparation bar', 'target' => 1],
            ['name' => 'Sortie cuisine', 'target' => 2],
            ['name' => 'Service en salle', 'target' => 3],
        ];
    }

    private function progressForStatus(OrderStatus $status): int
    {
        return match ($status) {
            OrderStatus::PENDING, OrderStatus::CANCELLED => 0,
            OrderStatus::IN_PREP => 1,
            OrderStatus::READY => 2,
            OrderStatus::SERVED, OrderStatus::PAYED => 3,
        };
    }

    private function statusForProgress(int $progress): OrderStepStatus
    {
        return match ($progress) {
            0 => OrderStepStatus::PENDING,
            1 => OrderStepStatus::IN_PREP,
            2 => OrderStepStatus::READY,
            default => OrderStepStatus::SERVED,
        };
    }
}
