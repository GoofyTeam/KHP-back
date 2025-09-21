<?php

namespace App\Http\Controllers;

use App\Enums\MenuServiceType;
use App\Enums\OrderHistoryAction;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\Preparation;
use App\Models\StepMenu;
use App\Services\OrderHistoryService;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

use function collect;

class OrderController extends Controller
{
    private const LOSS_REASON_KITCHEN = 'KITCHEN_LOSS';

    private const LOSS_REASON_SERVICE = 'SERVICE_LOSS';

    public function __construct(
        private UnitConversionService $unitConversionService,
        private OrderHistoryService $orderHistoryService,
    ) {}

    /**
     * Create a new order for the authenticated user's company.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'table_id' => [
                'required',
                'integer',
                Rule::exists('tables', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
            ],
        ]);

        $order = Order::query()->create([
            'table_id' => (int) $validated['table_id'],
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'pending_at' => now(),
        ]);

        $this->orderHistoryService->record(
            order: $order,
            action: OrderHistoryAction::ORDER_CREATED,
            user: $user,
            payload: [
                'table_id' => $order->table_id,
                'status' => $order->status->value,
            ],
        );

        $order->load(['table', 'steps.stepMenus.menu']);

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order,
        ], 201);
    }

    /**
     * Create a new step on the order with the provided menus.
     */
    public function storeStep(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        [$payload, $menus] = $this->validateMenuPayload($request, (int) $user->company_id);

        $step = DB::transaction(function () use ($order, $payload, $menus, $user) {
            $position = ((int) $order->steps()->max('position')) + 1;

            /** @var OrderStep $step */
            $step = $order->steps()->create([
                'position' => $position,
                'status' => OrderStepStatus::IN_PREP,
            ]);

            $this->orderHistoryService->record(
                order: $order,
                action: OrderHistoryAction::ORDER_STEP_CREATED,
                user: $user,
                orderStep: $step,
                payload: [
                    'position' => $position,
                ],
            );

            foreach ($payload as $menuData) {
                $menu = $menus->get($menuData['menu_id']);
                $status = ($menu && $menu->service_type === MenuServiceType::DIRECT)
                    ? StepMenuStatus::READY
                    : StepMenuStatus::IN_PREP;

                /** @var StepMenu $stepMenu */
                $stepMenu = $step->stepMenus()->create([
                    'menu_id' => $menuData['menu_id'],
                    'quantity' => $menuData['quantity'],
                    'status' => $status,
                    'note' => $menuData['note'] ?? null,
                ]);

                $this->orderHistoryService->record(
                    order: $order,
                    action: OrderHistoryAction::STEP_MENU_ADDED,
                    user: $user,
                    orderStep: $step,
                    stepMenu: $stepMenu,
                    payload: [
                        'menu_id' => $menuData['menu_id'],
                        'quantity' => $menuData['quantity'],
                        'status' => $status->value,
                        'note' => $menuData['note'] ?? null,
                    ],
                );
            }

            if ($order->pending_at === null) {
                $order->pending_at = now();
                $order->save();
            }

            $previousStatus = $step->status;
            $step->refreshStatusFromStepMenus();

            $step->refresh();

            if ($previousStatus !== $step->status) {
                $this->orderHistoryService->recordStepStatusChange(
                    order: $order,
                    step: $step,
                    from: $previousStatus,
                    to: $step->status,
                    user: $user,
                );
            }

            if ($step->status !== OrderStepStatus::SERVED && $step->served_at !== null) {
                $step->served_at = null;
                $step->save();
            }

            return $step->refresh()->load('stepMenus.menu');
        });

        return response()->json([
            'message' => 'Step created successfully.',
            'step' => $step,
        ], 201);
    }

    /**
     * Add a new menu to an existing step.
     */
    public function storeStepMenu(Request $request, Order $order, OrderStep $step): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        if ($step->order_id !== $order->id) {
            abort(404);
        }

        [$payload, $menu] = $this->validateStepMenuStorePayload($request, (int) $user->company_id);

        [$stepMenu, $step] = DB::transaction(function () use ($order, $step, $payload, $menu, $user) {
            $status = $menu->service_type === MenuServiceType::DIRECT
                ? StepMenuStatus::READY
                : StepMenuStatus::IN_PREP;

            /** @var StepMenu $stepMenu */
            $stepMenu = $step->stepMenus()->create([
                'menu_id' => $menu->id,
                'quantity' => (int) $payload['quantity'],
                'status' => $status,
                'note' => $payload['note'] ?? null,
            ]);

            $this->orderHistoryService->record(
                order: $order,
                action: OrderHistoryAction::STEP_MENU_ADDED,
                user: $user,
                orderStep: $step,
                stepMenu: $stepMenu,
                payload: [
                    'menu_id' => $menu->id,
                    'quantity' => (int) $payload['quantity'],
                    'status' => $status->value,
                    'note' => $payload['note'] ?? null,
                ],
            );

            $previousStepStatus = $step->status;
            $step->refreshStatusFromStepMenus();
            $step->refresh();

            if ($step->status !== OrderStepStatus::SERVED && $step->served_at !== null) {
                $step->served_at = null;
                $step->save();
                $step->refresh();
            }

            if ($previousStepStatus !== $step->status) {
                $this->orderHistoryService->recordStepStatusChange(
                    order: $order,
                    step: $step,
                    from: $previousStepStatus,
                    to: $step->status,
                    user: $user,
                );
            }

            return [
                $stepMenu->load('menu'),
                $step->load('stepMenus.menu'),
            ];
        });

        return response()->json([
            'message' => 'Menu added to step.',
            'step_menu' => $stepMenu,
            'step' => $step,
        ], 201);
    }

    /**
     * Cancel an existing menu from a step.
     */
    public function cancelStepMenu(Request $request, Order $order, StepMenu $stepMenu): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        /** @var OrderStep|null $step */
        $step = $stepMenu->step()->with('order')->first();

        if (! $step || $step->order_id !== $order->id) {
            abort(404);
        }

        /** @var Order $stepOrder */
        $stepOrder = $step->order;

        if ($stepOrder->company_id !== $user->company_id) {
            abort(404);
        }

        $stepMenu->loadMissing('menu');

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.$stepMenu->quantity],
            'unopened_return' => ['sometimes', 'boolean'],
        ]);

        $quantity = (int) ($validated['quantity'] ?? $stepMenu->quantity);
        $unopenedReturn = (bool) ($validated['unopened_return'] ?? false);

        /** @var Menu|null $menu */
        $menu = $stepMenu->menu()->with(['items.entity', 'items.location'])->first();

        if (! $menu) {
            abort(422, 'The menu associated with this step menu is invalid.');
        }

        $action = $this->determineCancellationAction($stepMenu, $menu, $unopenedReturn);

        $previousQuantity = $stepMenu->quantity;
        $previousStepStatus = $step->status;

        $historyReason = null;
        if ($action['type'] === 'loss') {
            $historyReason = $action['reason'] ?? null;
        } elseif ($action['type'] === 'return') {
            $historyReason = 'RETURN_ACCEPTED';
        }

        try {
            [$updatedStepMenu, $updatedStep] = DB::transaction(function () use ($order, $user, $stepMenu, $step, $menu, $quantity, $action, $previousQuantity, $previousStepStatus, $historyReason) {
                if ($action['type'] === 'loss') {
                    $this->recordMenuLosses($menu, $quantity, $action['reason']);
                }

                $remainingQuantity = $stepMenu->quantity - $quantity;

                $historyPayload = [
                    'menu_id' => $stepMenu->menu_id,
                    'step_menu_id' => $stepMenu->id,
                    'quantity_before' => $previousQuantity,
                    'canceled_quantity' => $quantity,
                    'type' => $action['type'],
                ];

                if ($action['type'] === 'return') {
                    $historyPayload['return_accepted'] = true;
                }

                if ($action['type'] === 'loss' && isset($action['reason'])) {
                    $historyPayload['loss_reason'] = $action['reason'];
                }

                if ($remainingQuantity <= 0) {
                    $this->orderHistoryService->record(
                        order: $order,
                        action: OrderHistoryAction::STEP_MENU_REMOVED,
                        user: $user,
                        orderStep: $step,
                        stepMenu: $stepMenu,
                        payload: array_merge($historyPayload, [
                            'quantity_after' => 0,
                        ]),
                        reason: $historyReason,
                    );

                    $stepMenu->delete();
                    $stepMenuModel = null;
                } else {
                    $stepMenu->quantity = $remainingQuantity;
                    $stepMenu->save();
                    $stepMenuModel = $stepMenu->refresh()->load('menu');

                    $this->orderHistoryService->record(
                        order: $order,
                        action: OrderHistoryAction::STEP_MENU_UPDATED,
                        user: $user,
                        orderStep: $step,
                        stepMenu: $stepMenu,
                        payload: array_merge($historyPayload, [
                            'quantity_after' => $remainingQuantity,
                        ]),
                        reason: $historyReason,
                    );
                }

                $stepModel = $this->finalizeStepState($step);

                if ($previousStepStatus !== $stepModel->status) {
                    $this->orderHistoryService->recordStepStatusChange(
                        order: $order,
                        step: $stepModel,
                        from: $previousStepStatus,
                        to: $stepModel->status,
                        user: $user,
                    );
                }

                return [$stepMenuModel, $stepModel];
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json([
            'message' => $action['type'] === 'return' ? 'Step menu cancellation accepted as unopened return.' : 'Step menu canceled successfully.',
            'step_menu' => $updatedStepMenu,
            'step' => $updatedStep,
            'canceled_quantity' => $quantity,
            'loss_recorded' => $action['type'] === 'loss',
            'loss_reason' => $action['type'] === 'loss' ? $action['reason'] : null,
            'return_accepted' => $action['type'] === 'return',
        ]);
    }

    /**
     * Marque un menu d'étape comme prêt.
     */
    public function markStepMenuReady(Request $request, Order $order, StepMenu $stepMenu): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        /** @var OrderStep|null $step */
        $step = $stepMenu->step()->with('order')->first();

        if (! $step || $step->order_id !== $order->id) {
            abort(404);
        }

        /** @var Order $stepOrder */
        $stepOrder = $step->order;

        if ($stepOrder->company_id !== $user->company_id) {
            abort(404);
        }

        if ($stepMenu->status !== StepMenuStatus::IN_PREP) {
            return response()->json([
                'message' => 'Only menus in preparation can be marked as ready.',
            ], 422);
        }

        $previousStatus = $stepMenu->status;
        $previousStepStatus = $step->status;

        $stepMenu->status = StepMenuStatus::READY;
        $stepMenu->save();

        $step->refreshStatusFromStepMenus();

        $this->orderHistoryService->recordStepMenuStatusChange(
            order: $order,
            stepMenu: $stepMenu,
            from: $previousStatus,
            to: StepMenuStatus::READY,
            user: $user,
        );

        if ($previousStepStatus !== $step->status) {
            $this->orderHistoryService->recordStepStatusChange(
                order: $order,
                step: $step,
                from: $previousStepStatus,
                to: $step->status,
                user: $user,
            );
        }

        $stepMenu->refresh()->load('menu');
        $step->refresh()->load('stepMenus.menu');

        return response()->json([
            'message' => 'Step menu marked as ready successfully.',
            'step_menu' => $stepMenu,
            'step' => $step,
        ]);
    }

    /**
     * Marque un menu d'étape comme servi.
     */
    public function markStepMenuServed(Request $request, Order $order, StepMenu $stepMenu): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        /** @var OrderStep|null $step */
        $step = $stepMenu->step()->with('order')->first();

        if (! $step || $step->order_id !== $order->id) {
            abort(404);
        }

        /** @var Order $stepOrder */
        $stepOrder = $step->order;

        if ($stepOrder->company_id !== $user->company_id) {
            abort(404);
        }

        if ($stepMenu->status !== StepMenuStatus::READY) {
            return response()->json([
                'message' => 'Only ready menus can be marked as served.',
            ], 422);
        }

        $previousStatus = $stepMenu->status;
        $previousStepStatus = $step->status;

        $stepMenu->status = StepMenuStatus::SERVED;
        $stepMenu->served_at = now();
        $stepMenu->save();

        $step->refreshStatusFromStepMenus();

        $step->refresh();

        if ($step->status === OrderStepStatus::SERVED && $step->served_at === null) {
            $step->served_at = now();
            $step->save();
            $step->refresh();
        }

        $this->orderHistoryService->recordStepMenuStatusChange(
            order: $order,
            stepMenu: $stepMenu,
            from: $previousStatus,
            to: StepMenuStatus::SERVED,
            user: $user,
        );

        if ($previousStepStatus !== $step->status) {
            $this->orderHistoryService->recordStepStatusChange(
                order: $order,
                step: $step,
                from: $previousStepStatus,
                to: $step->status,
                user: $user,
            );
        }

        $stepMenu->refresh()->load('menu');
        $step->load('stepMenus.menu');

        return response()->json([
            'message' => 'Step menu marked as served successfully.',
            'step_menu' => $stepMenu,
            'step' => $step,
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        $validated = $request->validate([
            'unopened_returns' => ['sometimes', 'array'],
            'unopened_returns.*' => ['integer'],
        ]);

        $order->load(['steps.stepMenus']);

        $unopenedReturns = collect($validated['unopened_returns'] ?? []);

        $previousStatus = $order->status;

        if ($unopenedReturns->isNotEmpty()) {
            $validStepMenuIds = $order->steps
                ->flatMap(static fn (OrderStep $step) => $step->stepMenus->pluck('id'))
                ->unique();

            $invalidIds = $unopenedReturns->diff($validStepMenuIds);

            if ($invalidIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'unopened_returns' => 'Some provided step menu identifiers are invalid for this order.',
                ]);
            }
        }

        $order->load(['steps.stepMenus.menu.items.entity', 'steps.stepMenus.menu.items.location']);

        try {
            [$lossStepMenuIds, $returnAcceptedIds] = DB::transaction(function () use ($order, $unopenedReturns, $user, $previousStatus) {
                $lossIds = [];
                $returnIds = [];

                foreach ($order->steps as $step) {
                    $previousStepStatus = $step->status;

                    foreach ($step->stepMenus as $stepMenu) {
                        /** @var Menu|null $menu */
                        $menu = $stepMenu->menu;
                        if (! $menu) {
                            throw new RuntimeException('A step menu is missing its associated menu.');
                        }

                        $unopenedReturn = $unopenedReturns->contains($stepMenu->id);
                        $action = $this->determineCancellationAction($stepMenu, $menu, $unopenedReturn);

                        $historyReason = null;
                        if ($action['type'] === 'loss') {
                            $historyReason = $action['reason'] ?? null;
                        } elseif ($action['type'] === 'return') {
                            $historyReason = 'RETURN_ACCEPTED';
                        }

                        $historyPayload = [
                            'menu_id' => $stepMenu->menu_id,
                            'step_menu_id' => $stepMenu->id,
                            'quantity_before' => $stepMenu->quantity,
                            'canceled_quantity' => $stepMenu->quantity,
                            'quantity_after' => 0,
                            'type' => $action['type'],
                        ];

                        if ($action['type'] === 'return') {
                            $historyPayload['return_accepted'] = true;
                        }

                        if ($action['type'] === 'loss' && isset($action['reason'])) {
                            $historyPayload['loss_reason'] = $action['reason'];
                        }

                        if ($action['type'] === 'loss') {
                            $this->recordMenuLosses($menu, $stepMenu->quantity, $action['reason']);
                            $lossIds[] = $stepMenu->id;
                        } elseif ($action['type'] === 'return') {
                            $returnIds[] = $stepMenu->id;
                        }

                        $this->orderHistoryService->record(
                            order: $order,
                            action: OrderHistoryAction::STEP_MENU_REMOVED,
                            user: $user,
                            orderStep: $step,
                            stepMenu: $stepMenu,
                            payload: $historyPayload,
                            reason: $historyReason,
                        );

                        $stepMenu->delete();
                    }

                    $stepModel = $this->finalizeStepState($step);

                    if ($previousStepStatus !== $stepModel->status) {
                        $this->orderHistoryService->recordStepStatusChange(
                            order: $order,
                            step: $stepModel,
                            from: $previousStepStatus,
                            to: $stepModel->status,
                            user: $user,
                        );
                    }
                }

                $order->status = OrderStatus::CANCELED;
                $order->canceled_at = now();
                $order->served_at = null;
                $order->payed_at = null;
                $order->save();

                if ($previousStatus !== $order->status) {
                    $this->orderHistoryService->recordOrderStatusChange(
                        order: $order,
                        from: $previousStatus,
                        to: $order->status,
                        user: $user,
                        additionalPayload: [
                            'loss_step_menu_ids' => $lossIds,
                            'return_step_menu_ids' => $returnIds,
                        ],
                        reason: 'ORDER_CANCELED',
                    );
                }

                return [$lossIds, $returnIds];
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        $order->refresh()->load('steps.stepMenus.menu');

        return response()->json([
            'message' => 'Order canceled successfully.',
            'order' => $order,
            'loss_step_menu_ids' => $lossStepMenuIds,
            'return_accepted_step_menu_ids' => $returnAcceptedIds,
        ]);
    }

    public function markPayed(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        $validated = $request->validate([
            'force' => ['sometimes', 'boolean'],
        ]);

        $force = (bool) ($validated['force'] ?? false);

        $order->load('steps.stepMenus');

        $stepMenus = $order->steps->flatMap(
            static fn (OrderStep $step) => $step->stepMenus,
        );

        $allServed = $stepMenus->isNotEmpty() && $stepMenus->every(
            static fn (StepMenu $stepMenu): bool => $stepMenu->status === StepMenuStatus::SERVED,
        );

        if (! $force && ! $allServed) {
            return response()->json([
                'message' => 'All menus must be served before marking the order as payed.',
            ], 422);
        }

        $previousStatus = $order->status;

        $order->status = OrderStatus::PAYED;

        if ($order->payed_at === null) {
            $order->payed_at = now();
        }

        $order->save();

        if ($previousStatus !== $order->status) {
            $this->orderHistoryService->recordOrderStatusChange(
                order: $order,
                from: $previousStatus,
                to: $order->status,
                user: $user,
                additionalPayload: [
                    'force' => $force,
                ],
                reason: $force ? 'PAYMENT_FORCED' : null,
            );
        }

        $order->load('steps.stepMenus.menu');

        return response()->json([
            'message' => 'Order marked as payed successfully.',
            'order' => $order,
        ]);
    }

    /**
     * @return array{0: array<int, array{menu_id: int, quantity: int, note: string|null}>, 1: \Illuminate\Support\Collection<int, Menu>}
     */
    private function validateMenuPayload(Request $request, int $companyId): array
    {
        $validated = $request->validate([
            'menus' => ['required', 'array', 'min:1'],
            'menus.*.menu_id' => [
                'required',
                'integer',
                Rule::exists('menus', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'menus.*.quantity' => ['required', 'integer', 'min:1'],
            'menus.*.note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $menus = Menu::query()
            ->whereIn('id', collect($validated['menus'])->pluck('menu_id'))
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        return [$validated['menus'], $menus];
    }

    /**
     * @return array{0: array{menu_id: int, quantity: int, note?: string|null}, 1: Menu}
     */
    private function validateStepMenuStorePayload(Request $request, int $companyId): array
    {
        $validated = $request->validate([
            'menu_id' => [
                'required',
                'integer',
                Rule::exists('menus', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        /** @var Menu|null $menu */
        $menu = Menu::query()
            ->where('id', $validated['menu_id'])
            ->where('company_id', $companyId)
            ->first();

        if (! $menu) {
            abort(422, 'The selected menu is invalid.');
        }

        $validated['quantity'] = (int) $validated['quantity'];

        return [$validated, $menu];
    }

    /**
     * @return array{type: 'simple'|'loss'|'return', reason?: string}
     */
    private function determineCancellationAction(StepMenu $stepMenu, Menu $menu, bool $unopenedReturn): array
    {
        $status = $stepMenu->status;
        $serviceType = $menu->service_type;

        if ($serviceType === MenuServiceType::PREP) {
            if ($status === StepMenuStatus::IN_PREP) {
                return ['type' => 'simple'];
            }

            return [
                'type' => 'loss',
                'reason' => self::LOSS_REASON_KITCHEN,
            ];
        }

        if ($serviceType === MenuServiceType::DIRECT) {
            if ($status === StepMenuStatus::SERVED) {
                if ($menu->is_returnable && $unopenedReturn) {
                    return ['type' => 'return'];
                }

                return [
                    'type' => 'loss',
                    'reason' => self::LOSS_REASON_SERVICE,
                ];
            }

            return ['type' => 'simple'];
        }

        return ['type' => 'simple'];
    }

    private function recordMenuLosses(Menu $menu, int $quantity, string $reason): void
    {
        $menu->loadMissing(['items.entity', 'items.location']);

        foreach ($menu->items as $item) {
            /** @var Ingredient|Preparation|null $entity */
            $entity = $item->entity;
            /** @var Location|null $location */
            $location = $item->location;

            if ($entity === null || $location === null) {
                throw new RuntimeException('Menu item is missing entity or location information for loss tracking.');
            }

            $itemUnit = $item->unit;
            $entityUnit = $entity->unit;

            $itemQuantity = (float) $item->quantity * $quantity;

            if ($itemQuantity <= 0) {
                continue;
            }

            $convertedQuantity = $itemUnit === $entityUnit
                ? $itemQuantity
                : $this->unitConversionService->convert($itemQuantity, $itemUnit, $entityUnit);

            if ($convertedQuantity <= 0) {
                continue;
            }

            try {
                $entity->recordLoss($location, $convertedQuantity, $reason);
            } catch (\Exception $exception) {
                throw new RuntimeException($exception->getMessage(), (int) $exception->getCode(), $exception);
            }
        }
    }

    private function finalizeStepState(OrderStep $step): OrderStep
    {
        $step->refreshStatusFromStepMenus();
        $step->refresh();

        if ($step->status !== OrderStepStatus::SERVED && $step->served_at !== null) {
            $step->served_at = null;
            $step->save();
            $step->refresh();
        }

        return $step->load('stepMenus.menu');
    }
}
