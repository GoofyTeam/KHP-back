<?php

namespace App\Services;

use App\Enums\OrderHistoryAction;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStep;
use App\Models\StepMenu;
use App\Models\User;

class OrderHistoryService
{
    public function record(
        Order $order,
        OrderHistoryAction $action,
        ?User $user = null,
        ?OrderStep $orderStep = null,
        ?StepMenu $stepMenu = null,
        array $payload = [],
        ?string $reason = null,
    ): OrderHistory {
        return OrderHistory::query()->create([
            'order_id' => $order->id,
            'order_step_id' => $orderStep?->id,
            'step_menu_id' => $stepMenu?->id,
            'company_id' => $order->company_id,
            'user_id' => $user !== null ? $user->id : auth()->id(),
            'action' => $action->value,
            'payload' => $payload === [] ? null : $payload,
            'reason' => $reason,
        ]);
    }

    public function recordOrderStatusChange(
        Order $order,
        OrderStatus $from,
        OrderStatus $to,
        ?User $user = null,
        array $additionalPayload = [],
        ?string $reason = null,
    ): OrderHistory {
        $payload = array_merge([
            'from' => $from->value,
            'to' => $to->value,
        ], $additionalPayload);

        return $this->record(
            order: $order,
            action: OrderHistoryAction::ORDER_STATUS_UPDATED,
            user: $user,
            payload: $payload,
            reason: $reason,
        );
    }

    public function recordStepStatusChange(
        Order $order,
        OrderStep $step,
        OrderStepStatus $from,
        OrderStepStatus $to,
        ?User $user = null,
    ): OrderHistory {
        return $this->record(
            order: $order,
            action: OrderHistoryAction::ORDER_STEP_STATUS_UPDATED,
            user: $user,
            orderStep: $step,
            payload: [
                'from' => $from->value,
                'to' => $to->value,
            ],
        );
    }

    public function recordStepMenuStatusChange(
        Order $order,
        StepMenu $stepMenu,
        StepMenuStatus $from,
        StepMenuStatus $to,
        ?User $user = null,
    ): OrderHistory {
        /** @var OrderStep|null $relatedStep */
        $relatedStep = $stepMenu->step;

        return $this->record(
            order: $order,
            action: OrderHistoryAction::STEP_MENU_STATUS_UPDATED,
            user: $user,
            orderStep: $relatedStep,
            stepMenu: $stepMenu,
            payload: [
                'from' => $from->value,
                'to' => $to->value,
            ],
        );
    }
}
