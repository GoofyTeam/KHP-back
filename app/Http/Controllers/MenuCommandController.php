<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Company;
use App\Models\MenuOrder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $order->load('menu.items');
        foreach ($order->menu->items as $item) {
            $entityClass = $item->entity_type;
            $entity = $entityClass::find($item->entity_id);
            if (! $entity) {
                continue;
            }
            $total = $item->quantity * $order->quantity;
            $stockService->remove($entity, $item->location_id, $order->menu->company_id, $total);
        }
    }

    /**
     * Restaure le stock lors de l'annulation d'une commande
     */
    private function revertOrder(MenuOrder $order, StockService $stockService): void
    {
        $order->load('menu.items');
        foreach ($order->menu->items as $item) {
            $entityClass = $item->entity_type;
            $entity = $entityClass::find($item->entity_id);
            if (! $entity) {
                continue;
            }
            $total = $item->quantity * $order->quantity;
            $stockService->add($entity, $item->location_id, $order->menu->company_id, $total);
        }
    }
}
