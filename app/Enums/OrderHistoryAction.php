<?php

namespace App\Enums;

enum OrderHistoryAction: string
{
    case ORDER_CREATED = 'order.created';
    case ORDER_STATUS_UPDATED = 'order.status_updated';
    case ORDER_STEP_CREATED = 'order_step.created';
    case ORDER_STEP_STATUS_UPDATED = 'order_step.status_updated';
    case STEP_MENU_ADDED = 'step_menu.added';
    case STEP_MENU_UPDATED = 'step_menu.updated';
    case STEP_MENU_REMOVED = 'step_menu.removed';
    case STEP_MENU_STATUS_UPDATED = 'step_menu.status_updated';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
