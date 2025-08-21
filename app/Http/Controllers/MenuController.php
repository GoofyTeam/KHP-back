<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.entity_type' => 'required|string',
            'items.*.entity_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
        ]);

        return DB::transaction(function () use ($data) {
            $menu = Menu::create([
                'company_id' => $data['company_id'],
                'name' => $data['name'],
            ]);

            foreach ($data['items'] as $item) {
                $menu->items()->create($item);
            }

            return $menu->load('items');
        });
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'items' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($data, $menu) {
            if (isset($data['name'])) {
                $menu->update(['name' => $data['name']]);
            }

            if (isset($data['items'])) {
                $menu->items()->delete();
                foreach ($data['items'] as $item) {
                    $menu->items()->create($item);
                }
            }

            return $menu->load('items');
        });
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return response()->json(['message' => 'Menu supprimé']);
    }

    public function order(Menu $menu, Request $request)
    {
        DB::transaction(function () use ($menu, $request) {
            /** @var MenuItem $item */
            foreach ($menu->items as $item) {
                $entity = $item->entity;

                $location = Location::findOrFail($request->location_id);

                $pivot = $entity->locations()->where('location_id', $location->id)->first();
                $currentQty = $pivot?->pivot->quantity ?? 0;

                $entity->locations()->updateExistingPivot($location->id, [
                    'quantity' => max(0, $currentQty - $item->quantity),
                ]);
            }
        });

        return response()->json(['message' => 'Commande effectuée']);
    }

    public function cancel(Menu $menu, Request $request)
    {
        DB::transaction(function () use ($menu, $request) {
            /** @var MenuItem $item */
            foreach ($menu->items as $item) {
                $entity = $item->entity;

                $location = Location::findOrFail($request->location_id);

                $pivot = $entity->locations()->where('location_id', $location->id)->first();
                $currentQty = $pivot?->pivot->quantity ?? 0;

                $entity->locations()->updateExistingPivot($location->id, [
                    'quantity' => $currentQty + $item->quantity,
                ]);
            }
        });

        return response()->json(['message' => 'Commande annulée et stock restauré']);
    }
}
