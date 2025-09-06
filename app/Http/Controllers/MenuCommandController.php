<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuOrder;
use App\Models\Preparation;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MenuCommandController extends Controller
{
    /**
     * Cas métier : Création d'une commande de menu
     *
     * Use cases :
     * - Enregistrer une commande pour un menu
     * - Appliquer immédiatement les retraits de stock si l'option entreprise l'autorise
     */
    public function store(Request $request, Menu $menu, StockService $stockService): JsonResponse
    {
        $user = $request->user();
        if ($menu->company_id !== $user->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $quantity = $validated['quantity'] ?? 1;

        if (! $menu->hasSufficientStock($quantity)) {
            return response()->json([
                'message' => 'Insufficient stock for this menu order',
            ], 422);
        }

        /** @var Company $company */
        $company = $user->company;
        $status = $company->auto_complete_menu_orders ? 'completed' : 'pending';

        $order = MenuOrder::create([
            'menu_id' => $menu->id,
            'status' => $status,
            'quantity' => $quantity,
        ]);

        if ($status === 'completed') {
            $this->applyOrder($order, $stockService);
            $menu->refreshAvailability();
        }

        return response()->json([
            'message' => 'Order created',
            'order' => $order,
        ], 201);
    }

    /**
     * Cas métier : Changement de statut d'une commande
     *
     * Use cases :
     * - Passer de "pending" à "completed" pour retirer le stock
     * - Empêcher les changements par une autre entreprise
     */
    public function updateStatus(Request $request, int $id, StockService $stockService): JsonResponse
    {
        $user = $request->user();

        $order = MenuOrder::findOrFail($id);
        if ($order->menu->company_id !== $user->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'completed'])],
        ]);

        $previous = $order->status;
        $order->status = $validated['status'];
        $order->save();

        if ($previous !== 'completed' && $order->status === 'completed') {
            $this->applyOrder($order, $stockService);
            $order->menu->refreshAvailability();
        }

        return response()->json([
            'message' => 'Order updated',
            'order' => $order,
        ]);
    }

    /**
     * Cas métier : Annulation d'une commande
     *
     * Use cases :
     * - Rétablir le stock si la commande était terminée
     */
    public function cancel(Request $request, int $id, StockService $stockService): JsonResponse
    {
        $user = $request->user();
        $order = MenuOrder::findOrFail($id);
        if ($order->menu->company_id !== $user->company_id) {
            abort(403);
        }

        if ($order->status === 'completed') {
            $this->revertOrder($order, $stockService);
            $order->menu->refreshAvailability();
        }

        $order->status = 'canceled';
        $order->save();

        return response()->json([
            'message' => 'Order canceled',
            'order' => $order,
        ]);
    }

    /**
     * Applique les effets de la commande sur le stock
     */
    private function applyOrder(MenuOrder $order, StockService $stockService): void
    {
        try {
            DB::transaction(function () use ($order, $stockService) {
                $order->load('menu.items.entity');
                foreach ($order->menu->items as $item) {
                    $entity = $item->entity;
                    if (! $entity instanceof Ingredient && ! $entity instanceof Preparation) {
                        continue;
                    }
                    $total = $item->quantity * $order->quantity;
                    $stockService->remove($entity, $item->location_id, $order->menu->company_id, $total, 'menu order');
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to apply menu order', [
                'order_id' => $order->id,
                'exception' => $e,
            ]);
            abort(500, 'Failed to apply menu order: '.$e->getMessage());
        }
    }

    /**
     * Restaure le stock lors de l'annulation d'une commande
     */
    private function revertOrder(MenuOrder $order, StockService $stockService): void
    {
        try {
            DB::transaction(function () use ($order, $stockService) {
                $order->load('menu.items.entity');
                foreach ($order->menu->items as $item) {
                    $entity = $item->entity;
                    if (! $entity instanceof Ingredient && ! $entity instanceof Preparation) {
                        continue;
                    }
                    $total = $item->quantity * $order->quantity;
                    $stockService->add($entity, $item->location_id, $order->menu->company_id, $total, 'menu order cancellation');
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to revert menu order', [
                'order_id' => $order->id,
                'exception' => $e,
            ]);
            abort(500, 'Failed to revert menu order: '.$e->getMessage());
        }
    }
}
