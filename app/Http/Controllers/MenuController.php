<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Preparation;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    /**
     * Cas métier : Création d'un nouveau menu
     *
     * Use cases :
     * - Ajouter un menu au catalogue de l'entreprise
     * - Définir les ingrédients/préparations avec leurs quantités et leur localisation
     */
    public function store(Request $request, ImageService $imageService): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menus')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'description' => ['nullable', 'string'],
            'is_a_la_carte' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'image_url' => ['sometimes', 'nullable', 'url'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.entity_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($request, $user) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $entityType = $request->input("items.$index.entity_type");
                    $table = $entityType === 'ingredient' ? 'ingredients' : 'preparations';

                    $rule = Rule::exists($table, 'id')->where(fn ($q) => $q->where('company_id', $user->company_id));
                    if (! Validator::make(['entity_id' => $value], ['entity_id' => [$rule]])->passes()) {
                        $fail('The selected entity_id is invalid.');
                    }
                },
            ],
            'items.*.entity_type' => ['required', 'string', 'in:ingredient,preparation'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required', 'string', Rule::in(MeasurementUnit::values())],
            'items.*.location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id))],
        ]);

        $this->ensureUniqueItems($validated['items']);

        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $imageService->store($request->file('image'), 'menus');
        } elseif ($request->filled('image_url')) {
            $imagePath = $imageService->storeFromUrl($request->input('image_url'), 'menus');
        }

        $menu = Menu::create([
            'company_id' => $user->company_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_a_la_carte' => $validated['is_a_la_carte'] ?? false,
            'image_url' => $imagePath,
        ]);

        foreach ($validated['items'] as $item) {
            $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;

            if (! $entityClass::where('id', $item['entity_id'])->where('company_id', $user->company_id)->exists()) {
                throw ValidationException::withMessages([
                    'items' => ['The selected entity does not belong to this company.'],
                ]);
            }

            MenuItem::create([
                'menu_id' => $menu->id,
                'entity_id' => $item['entity_id'],
                'entity_type' => $entityClass,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'location_id' => $item['location_id'],
            ]);
        }

        $menu->refreshAvailability();

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
     * - Ajouter, retirer ou modifier des éléments (et leur localisation) sans renvoyer la liste complète
     */
    public function update(Request $request, int $id, ImageService $imageService): JsonResponse
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
            'description' => ['sometimes', 'nullable', 'string'],
            'is_a_la_carte' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'image_url' => ['sometimes', 'nullable', 'url'],
            'items_to_add' => ['sometimes', 'array', 'min:1'],
            'items_to_add.*.entity_id' => [
                'required_with:items_to_add',
                'integer',
                function ($attribute, $value, $fail) use ($request, $user) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $entityType = $request->input("items_to_add.$index.entity_type");
                    $table = $entityType === 'ingredient' ? 'ingredients' : 'preparations';

                    $rule = Rule::exists($table, 'id')->where(fn ($q) => $q->where('company_id', $user->company_id));
                    if (! Validator::make(['entity_id' => $value], ['entity_id' => [$rule]])->passes()) {
                        $fail('The selected entity_id is invalid.');
                    }
                },
            ],
            'items_to_add.*.entity_type' => ['required_with:items_to_add', 'string', 'in:ingredient,preparation'],
            'items_to_add.*.quantity' => ['required_with:items_to_add', 'numeric', 'min:0.01'],
            'items_to_add.*.unit' => ['required_with:items_to_add', 'string', Rule::in(MeasurementUnit::values())],
            'items_to_add.*.location_id' => ['required_with:items_to_add', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id))],
            'items_to_remove' => ['sometimes', 'array', 'min:1'],
            'items_to_remove.*.entity_id' => ['required_with:items_to_remove', 'integer'],
            'items_to_remove.*.entity_type' => ['required_with:items_to_remove', 'string', 'in:ingredient,preparation'],
            'items_to_update' => ['sometimes', 'array', 'min:1'],
            'items_to_update.*.entity_id' => [
                'required_with:items_to_update',
                'integer',
                function ($attribute, $value, $fail) use ($request, $user) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $entityType = $request->input("items_to_update.$index.entity_type");
                    $table = $entityType === 'ingredient' ? 'ingredients' : 'preparations';

                    $rule = Rule::exists($table, 'id')->where(fn ($q) => $q->where('company_id', $user->company_id));
                    if (! Validator::make(['entity_id' => $value], ['entity_id' => [$rule]])->passes()) {
                        $fail('The selected entity_id is invalid.');
                    }
                },
            ],
            'items_to_update.*.entity_type' => ['required_with:items_to_update', 'string', 'in:ingredient,preparation'],
            'items_to_update.*.quantity' => ['required_with:items_to_update', 'numeric', 'min:0.01'],
            'items_to_update.*.unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
            'items_to_update.*.location_id' => ['sometimes', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id))],
        ]);

        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        if ($request->hasFile('image')) {
            $menu->image_url = $imageService->store($request->file('image'), 'menus');
        } elseif ($request->filled('image_url')) {
            $menu->image_url = $imageService->storeFromUrl($request->input('image_url'), 'menus');
        }

        if (array_key_exists('name', $validated)) {
            $menu->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $menu->description = $validated['description'];
        }
        if (array_key_exists('is_a_la_carte', $validated)) {
            $menu->is_a_la_carte = $validated['is_a_la_carte'];
        }
        $menu->save();

        // Remove specified items
        if (! empty($validated['items_to_remove'])) {
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
        if (! empty($validated['items_to_add'])) {
            $this->ensureUniqueItems($validated['items_to_add']);

            foreach ($validated['items_to_add'] as $item) {
                $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;

                if ($menu->items()->where('entity_type', $entityClass)->where('entity_id', $item['entity_id'])->exists()) {
                    throw ValidationException::withMessages([
                        'items_to_add' => ['Duplicate items are not allowed in a menu.'],
                    ]);
                }

                if (! $entityClass::where('id', $item['entity_id'])->where('company_id', $user->company_id)->exists()) {
                    throw ValidationException::withMessages([
                        'items_to_add' => ['The selected entity does not belong to this company.'],
                    ]);
                }

                MenuItem::create([
                    'menu_id' => $menu->id,
                    'entity_id' => $item['entity_id'],
                    'entity_type' => $entityClass,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'location_id' => $item['location_id'],
                ]);
            }
        }

        // Update quantity or unit of existing items
        if (! empty($validated['items_to_update'])) {
            foreach ($validated['items_to_update'] as $item) {
                $entityClass = $item['entity_type'] === 'ingredient' ? Ingredient::class : Preparation::class;

                if (! $entityClass::where('id', $item['entity_id'])->where('company_id', $user->company_id)->exists()) {
                    throw ValidationException::withMessages([
                        'items_to_update' => ['The selected entity does not belong to this company.'],
                    ]);
                }

                /** @var MenuItem|null $menuItem */
                $menuItem = $menu->items()
                    ->where('entity_type', $entityClass)
                    ->where('entity_id', $item['entity_id'])
                    ->first();

                if (! $menuItem) {
                    throw ValidationException::withMessages([
                        'items_to_update' => ['Item not found in this menu.'],
                    ]);
                }

                $menuItem->quantity = $item['quantity'];
                if (array_key_exists('unit', $item)) {
                    $menuItem->unit = $item['unit'];
                }
                if (array_key_exists('location_id', $item)) {
                    $menuItem->location_id = $item['location_id'];
                }
                $menuItem->save();
            }
            $menu->load('items');
        }

        $menu->refreshAvailability();

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
     * @param  array<array<string, mixed>>  $items
     *
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
