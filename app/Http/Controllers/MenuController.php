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
     * - Remplacer les éléments qui le composent
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
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.entity_id' => ['required_with:items', 'integer'],
            'items.*.entity_type' => ['required_with:items', 'string', 'in:ingredient,preparation'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required_with:items', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        if (array_key_exists('name', $validated)) {
            $menu->name = $validated['name'];
        }
        $menu->save();

        if (isset($validated['items'])) {
            $this->ensureUniqueItems($validated['items']);
            $menu->items()->delete();
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
