<?php

namespace App\Http\Controllers;

use App\Enums\MenuServiceType;
use App\Enums\OrderStatus;
use App\Enums\OrderStepStatus;
use App\Enums\StepMenuStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\StepMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
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

        $step = DB::transaction(function () use ($order, $payload, $menus) {
            $position = ((int) $order->steps()->max('position')) + 1;

            /** @var OrderStep $step */
            $step = $order->steps()->create([
                'position' => $position,
                'status' => OrderStepStatus::IN_PREP,
            ]);

            foreach ($payload as $menuData) {
                $menu = $menus->get($menuData['menu_id']);
                $status = ($menu && $menu->service_type === MenuServiceType::DIRECT)
                    ? StepMenuStatus::READY
                    : StepMenuStatus::IN_PREP;

                $step->stepMenus()->create([
                    'menu_id' => $menuData['menu_id'],
                    'quantity' => $menuData['quantity'],
                    'status' => $status,
                    'note' => $menuData['note'] ?? null,
                ]);
            }

            if ($order->pending_at === null) {
                $order->pending_at = now();
                $order->save();
            }

            $step->refreshStatusFromStepMenus();

            $step->refresh();

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
     * Synchronise the menus of an existing step in a single request.
     */
    public function syncStepMenus(Request $request, Order $order, OrderStep $step): JsonResponse
    {
        $user = $request->user();

        if ($order->company_id !== $user->company_id) {
            abort(404);
        }

        if ($step->order_id !== $order->id) {
            abort(404);
        }

        [$payload, $menus] = $this->validateStepMenuSyncPayload(
            $request,
            (int) $user->company_id,
            $step
        );

        $step = DB::transaction(function () use ($step, $payload, $menus) {
            /** @var \Illuminate\Support\Collection<int, StepMenu> $existingStepMenus */
            $existingStepMenus = $step->stepMenus()->get()->keyBy('id');

            foreach ($payload as $menuData) {
                if (isset($menuData['step_menu_id'])) {
                    $stepMenuId = (int) $menuData['step_menu_id'];
                    /** @var StepMenu|null $stepMenu */
                    $stepMenu = $existingStepMenus->get($stepMenuId);

                    if (! $stepMenu) {
                        continue;
                    }

                    if (array_key_exists('quantity', $menuData) && (int) $menuData['quantity'] === 0) {
                        $stepMenu->delete();
                        $existingStepMenus->forget($stepMenuId);

                        continue;
                    }

                    if (array_key_exists('quantity', $menuData)) {
                        $stepMenu->quantity = (int) $menuData['quantity'];
                    }

                    if (array_key_exists('note', $menuData)) {
                        $stepMenu->note = $menuData['note'];
                    }

                    $stepMenu->save();

                    continue;
                }

                $menuId = (int) $menuData['menu_id'];
                $menu = $menus->get($menuId);

                if (! $menu) {
                    continue;
                }

                $status = $menu->service_type === MenuServiceType::DIRECT
                    ? StepMenuStatus::READY
                    : StepMenuStatus::IN_PREP;

                /** @var StepMenu $created */
                $created = $step->stepMenus()->create([
                    'menu_id' => $menuId,
                    'quantity' => (int) $menuData['quantity'],
                    'status' => $status,
                    'note' => $menuData['note'] ?? null,
                ]);

                $existingStepMenus->put($created->id, $created);
            }

            $step->refreshStatusFromStepMenus();
            $step->refresh();

            if ($step->status !== OrderStepStatus::SERVED && $step->served_at !== null) {
                $step->served_at = null;
                $step->save();
                $step->refresh();
            }

            return $step->load('stepMenus.menu');
        });

        return response()->json([
            'message' => 'Step menus updated successfully.',
            'step' => $step,
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

        $stepMenu->status = StepMenuStatus::READY;
        $stepMenu->save();

        $step->refreshStatusFromStepMenus();

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

        $stepMenu->refresh()->load('menu');
        $step->load('stepMenus.menu');

        return response()->json([
            'message' => 'Step menu marked as served successfully.',
            'step_menu' => $stepMenu,
            'step' => $step,
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

        $order->status = OrderStatus::PAYED;

        if ($order->payed_at === null) {
            $order->payed_at = now();
        }

        $order->save();

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
     * @return array{0: array<int, array<string, mixed>>, 1: \Illuminate\Support\Collection<int, Menu>}
     */
    private function validateStepMenuSyncPayload(Request $request, int $companyId, OrderStep $step): array
    {
        $validator = Validator::make($request->all(), [
            'menus' => ['required', 'array', 'min:1'],
            'menus.*.step_menu_id' => [
                'sometimes',
                'integer',
                Rule::exists('step_menus', 'id')->where(fn ($query) => $query->where('order_step_id', $step->id)),
            ],
            'menus.*.menu_id' => [
                'sometimes',
                'integer',
                Rule::exists('menus', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'menus.*.quantity' => ['sometimes', 'integer', 'min:0'],
            'menus.*.note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $menus = $request->input('menus', []);

            foreach ($menus as $index => $menuData) {
                if (! isset($menuData['step_menu_id'])) {
                    if (! isset($menuData['menu_id'])) {
                        $validator->errors()->add("menus.{$index}.menu_id", 'The menu id field is required.');

                        continue;
                    }

                    if (! array_key_exists('quantity', $menuData)) {
                        $validator->errors()->add("menus.{$index}.quantity", 'The quantity field is required.');

                        continue;
                    }

                    if ((int) $menuData['quantity'] < 1) {
                        $validator->errors()->add("menus.{$index}.quantity", 'The quantity must be at least 1.');
                    }

                    continue;
                }

                if (array_key_exists('quantity', $menuData) && (int) $menuData['quantity'] < 0) {
                    $validator->errors()->add("menus.{$index}.quantity", 'The quantity must be at least 0.');
                }
            }
        });

        $validated = $validator->validate();

        $menuIds = collect($validated['menus'])
            ->filter(static fn (array $menuData): bool => ! isset($menuData['step_menu_id']))
            ->pluck('menu_id')
            ->unique()
            ->values();

        $menus = Menu::query()
            ->whereIn('id', $menuIds)
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        return [$validated['menus'], $menus];
    }
}
