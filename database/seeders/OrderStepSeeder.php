<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Models\Order;
use App\Models\OrderStep;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use Illuminate\Database\Seeder;

class OrderStepSeeder extends Seeder
{
    use FiltersSeedableCompanies;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::query()
            ->whereHas('company', fn ($query) => $query->whereNotIn('name', $this->excludedCompanyNames()))
            ->withCount('steps')
            ->get();

        foreach ($orders as $order) {
            if ($order->steps_count > 0) {
                continue;
            }

            $progress = $this->progressForStatus($order->status);
            $targets = collect($this->stepTargets());

            $targets->each(function (int $target, int $index) use ($order, $progress): void {
                $status = $this->statusForProgress(min($progress, $target));

                OrderStep::query()->create([
                    'order_id' => $order->id,
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
     * @return array<int, int>
     */
    private function stepTargets(): array
    {
        return [1, 2, 3];
    }

    private function progressForStatus(OrderStatus $status): int
    {
        return match ($status) {
            OrderStatus::PENDING, OrderStatus::CANCELED => 0,
            OrderStatus::SERVED => 1,
            OrderStatus::PAYED => 2,
        };
    }

    private function statusForProgress(int $progress): OrderStepStatus
    {
        return match ($progress) {
            0 => OrderStepStatus::IN_PREP,
            1 => OrderStepStatus::READY,
            default => OrderStepStatus::SERVED,
        };
    }
}
