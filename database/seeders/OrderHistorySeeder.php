<?php

namespace Database\Seeders;

use App\Enums\OrderHistoryAction;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\StepMenu;
use App\Models\User;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use App\Services\OrderHistoryService;
use Illuminate\Database\Seeder;

class OrderHistorySeeder extends Seeder
{
    use FiltersSeedableCompanies;

    public function run(): void
    {
        /** @var OrderHistoryService $historyService */
        $historyService = app(OrderHistoryService::class);

        $orders = Order::query()
            ->whereHas('company', fn ($query) => $query->whereNotIn('name', $this->excludedCompanyNames()))
            ->with(['user', 'steps.stepMenus'])
            ->get();

        foreach ($orders as $order) {
            if ($order->histories()->exists()) {
                continue;
            }

            $user = $order->user;

            $historyService->record(
                order: $order,
                action: OrderHistoryAction::ORDER_CREATED,
                user: $user,
                payload: [
                    'table_id' => $order->table_id,
                    'status' => $order->status->value,
                ],
            );

            /** @var OrderStep $step */
            foreach ($order->steps as $step) {
                $historyService->record(
                    order: $order,
                    action: OrderHistoryAction::ORDER_STEP_CREATED,
                    user: $user,
                    orderStep: $step,
                    payload: [
                        'position' => $step->position,
                    ],
                );

                $this->seedStepStatusHistory($historyService, $order, $step, $user);

                /** @var StepMenu $stepMenu */
                foreach ($step->stepMenus as $stepMenu) {
                    $historyService->record(
                        order: $order,
                        action: OrderHistoryAction::STEP_MENU_ADDED,
                        user: $user,
                        orderStep: $step,
                        stepMenu: $stepMenu,
                        payload: [
                            'menu_id' => $stepMenu->menu_id,
                            'quantity' => $stepMenu->quantity,
                            'status' => $stepMenu->status->value,
                            'note' => $stepMenu->note,
                        ],
                    );

                    $this->seedStepMenuStatusHistory($historyService, $order, $step, $stepMenu, $user);
                }
            }

            $this->seedOrderStatusHistory($historyService, $order, $user);
        }
    }

    private function seedStepStatusHistory(OrderHistoryService $historyService, Order $order, OrderStep $step, ?User $user): void
    {
        foreach ($this->stepStatusTransitions($step->status) as [$from, $to]) {
            $historyService->recordStepStatusChange(
                order: $order,
                step: $step,
                from: $from,
                to: $to,
                user: $user,
            );
        }
    }

    /**
     * @return array<int, array{0: OrderStepStatus, 1: OrderStepStatus}>
     */
    private function stepStatusTransitions(OrderStepStatus $status): array
    {
        return match ($status) {
            OrderStepStatus::IN_PREP => [],
            OrderStepStatus::READY => [
                [OrderStepStatus::IN_PREP, OrderStepStatus::READY],
            ],
            OrderStepStatus::SERVED => [
                [OrderStepStatus::IN_PREP, OrderStepStatus::READY],
                [OrderStepStatus::READY, OrderStepStatus::SERVED],
            ],
        };
    }

    private function seedStepMenuStatusHistory(
        OrderHistoryService $historyService,
        Order $order,
        OrderStep $step,
        StepMenu $stepMenu,
        ?User $user,
    ): void {
        foreach ($this->stepMenuStatusTransitions($stepMenu->status) as [$from, $to]) {
            $historyService->recordStepMenuStatusChange(
                order: $order,
                stepMenu: $stepMenu,
                from: $from,
                to: $to,
                user: $user,
            );
        }
    }

    /**
     * @return array<int, array{0: StepMenuStatus, 1: StepMenuStatus}>
     */
    private function stepMenuStatusTransitions(StepMenuStatus $status): array
    {
        return match ($status) {
            StepMenuStatus::IN_PREP => [],
            StepMenuStatus::READY => [
                [StepMenuStatus::IN_PREP, StepMenuStatus::READY],
            ],
            StepMenuStatus::SERVED => [
                [StepMenuStatus::IN_PREP, StepMenuStatus::READY],
                [StepMenuStatus::READY, StepMenuStatus::SERVED],
            ],
        };
    }

    private function seedOrderStatusHistory(OrderHistoryService $historyService, Order $order, ?User $user): void
    {
        foreach ($this->orderStatusTransitions($order) as $transition) {
            $historyService->recordOrderStatusChange(
                order: $order,
                from: $transition['from'],
                to: $transition['to'],
                user: $user,
                additionalPayload: $transition['payload'],
                reason: $transition['reason'],
            );
        }
    }

    /**
     * @return array<int, array{from: OrderStatus, to: OrderStatus, payload: array<string, mixed>, reason: string|null}>
     */
    private function orderStatusTransitions(Order $order): array
    {
        return match ($order->status) {
            OrderStatus::PENDING => [],
            OrderStatus::SERVED => [
                [
                    'from' => OrderStatus::PENDING,
                    'to' => OrderStatus::SERVED,
                    'payload' => [],
                    'reason' => null,
                ],
            ],
            OrderStatus::PAYED => [
                [
                    'from' => OrderStatus::PENDING,
                    'to' => OrderStatus::SERVED,
                    'payload' => [],
                    'reason' => null,
                ],
                [
                    'from' => OrderStatus::SERVED,
                    'to' => OrderStatus::PAYED,
                    'payload' => ['force' => false],
                    'reason' => null,
                ],
            ],
            OrderStatus::CANCELED => [
                [
                    'from' => OrderStatus::PENDING,
                    'to' => OrderStatus::CANCELED,
                    'payload' => [
                        'loss_step_menu_ids' => [],
                        'return_step_menu_ids' => [],
                    ],
                    'reason' => 'ORDER_CANCELED',
                ],
            ],
        };
    }
}
