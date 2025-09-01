<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Preparation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    /**
     * Cas métier : Création d'un nouveau menu
     *
     * Use cases :
     * - Ajouter un menu au catalogue de l'entreprise
     * - Définir les ingrédients/préparations et leurs quantités
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menus')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.entity_id' => ['required', 'integer'],
            'items.*.entity_type' => ['required', 'string', 'in:ingredient,preparation'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        $this->ensureUniqueItems($validated['items']);

        $menu = Menu::create([
            'company_id' => $user->company_id,
            'name' => $validated['name'],
        ]);

        foreach ($validated['items'] as $item) {
            $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;
            MenuItem::create([
                'menu_id' => $menu->id,
                'entity_id' => $item['entity_id'],
                'entity_type' => $entityClass,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
            ]);
        }

        return response()->json([
            'message' => 'Menu created',
            'menu' => $menu->load('items.entity'),
        ], 201);
    }

    /**
     * Cas métier : Mise à jour d'un menu existant
     *
     * Use cases :
     * - Modifier le nom du menu
     * - Ajouter, retirer ou modifier des éléments sans renvoyer la liste complète
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $menu = Menu::where('id', $id)->where('company_id', $user->company_id)->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('menus')->where(fn ($q) => $q->where('company_id', $user->company_id))->ignore($menu->id),
            ],
            'items_to_add' => ['sometimes', 'array', 'min:1'],
            'items_to_add.*.entity_id' => ['required_with:items_to_add', 'integer'],
            'items_to_add.*.entity_type' => ['required_with:items_to_add', 'string', 'in:ingredient,preparation'],
            'items_to_add.*.quantity' => ['required_with:items_to_add', 'numeric', 'min:0.01'],
            'items_to_add.*.unit' => ['required_with:items_to_add', 'string', Rule::in(MeasurementUnit::values())],
            'items_to_remove' => ['sometimes', 'array', 'min:1'],
            'items_to_remove.*.entity_id' => ['required_with:items_to_remove', 'integer'],
            'items_to_remove.*.entity_type' => ['required_with:items_to_remove', 'string', 'in:ingredient,preparation'],
            'items_to_update' => ['sometimes', 'array', 'min:1'],
            'items_to_update.*.entity_id' => ['required_with:items_to_update', 'integer'],
            'items_to_update.*.entity_type' => ['required_with:items_to_update', 'string', 'in:ingredient,preparation'],
            'items_to_update.*.quantity' => ['required_with:items_to_update', 'numeric', 'min:0.01'],
            'items_to_update.*.unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        if (array_key_exists('name', $validated)) {
            $menu->name = $validated['name'];
        }
        $menu->save();

        // Remove specified items
        if (!empty($validated['items_to_remove'])) {
            foreach ($validated['items_to_remove'] as $item) {
                $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;
                $menu->items()
                    ->where('entity_type', $entityClass)
                    ->where('entity_id', $item['entity_id'])
                    ->delete();
            }
            $menu->load('items');
        }

        // Add new items while ensuring no duplicates
        if (!empty($validated['items_to_add'])) {
            $this->ensureUniqueItems($validated['items_to_add']);

            foreach ($validated['items_to_add'] as $item) {
                $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;

                if ($menu->items()->where('entity_type', $entityClass)->where('entity_id', $item['entity_id'])->exists()) {
                    throw ValidationException::withMessages([
                        'items_to_add' => ['Duplicate items are not allowed in a menu.'],
                    ]);
                }

                MenuItem::create([
                    'menu_id' => $menu->id,
                    'entity_id' => $item['entity_id'],
                    'entity_type' => $entityClass,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);
            }
        }

        // Update quantity or unit of existing items
        if (!empty($validated['items_to_update'])) {
            foreach ($validated['items_to_update'] as $item) {
                $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;

                /** @var MenuItem|null $menuItem */
                $menuItem = $menu->items()
                    ->where('entity_type', $entityClass)
                    ->where('entity_id', $item['entity_id'])
                    ->first();

                if (!$menuItem) {
                    throw ValidationException::withMessages([
                        'items_to_update' => ['Item not found in this menu.'],
                    ]);
                }

                $menuItem->quantity = $item['quantity'];
                if (array_key_exists('unit', $item)) {
                    $menuItem->unit = $item['unit'];
                }
                $menuItem->save();
            }
            $menu->load('items');
        }

        return response()->json([
            'message' => 'Menu updated',
            'menu' => $menu->load('items.entity'),
        ], 200);
    }

    /**
     * Cas métier : Suppression d'un menu
     *
     * Use cases :
     * - Retirer un menu du catalogue
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $menu = Menu::where('id', $id)->where('company_id', $user->company_id)->firstOrFail();
        $menu->delete();

        return response()->json(null, 204);
    }

    /**
     * Vérifie l'unicité des éléments dans un menu.
     *
     * @param array<array<string, mixed>> $items
     * @throws ValidationException
     */
    private function ensureUniqueItems(array $items): void
    {
        $seen = [];
        foreach ($items as $item) {
            $key = $item['entity_type'].'-'.$item['entity_id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'items' => ['Duplicate items are not allowed in a menu.'],
                ]);
            }
            $seen[$key] = true;
        }
    }
}
