<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
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
            'menu_type_id' => [
                'required',
                'integer',
                Rule::exists('menu_types', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'service_type' => ['required', 'string', Rule::in(MenuServiceType::values())],
            'is_returnable' => ['required', 'boolean'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('menu_categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
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
            'menu_type_id' => $validated['menu_type_id'],
            'public_priority' => $validated['priority'] ?? 0,
            'service_type' => $validated['service_type'],
            'is_returnable' => $validated['is_returnable'],
            'price' => $validated['price'],
        ]);

        if (! empty($validated['category_ids'])) {
            $menu->categories()->sync($validated['category_ids']);
        }

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

        return response()->json([
            'message' => 'Menu created',
            'menu' => $menu->load('items.entity', 'categories', 'menuType'),
        ], 201);
    }

    /**
     * Cas métier : Mise à jour d'un menu existant en remplaçant intégralement ses données
     *
     * Use cases :
     * - Pré-remplir un formulaire avec les informations du menu puis renvoyer la liste complète modifiée
     * - Remplacer en une requête le nom, la description, le type, les catégories et les items
     * - Éviter la complexité côté front de gérer des ajouts ou suppressions incrémentaux
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
            'menu_type_id' => [
                'sometimes',
                'integer',
                Rule::exists('menu_types', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'service_type' => ['sometimes', 'string', Rule::in(MenuServiceType::values())],
            'is_returnable' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('menu_categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
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

        $willBePublic = array_key_exists('is_a_la_carte', $validated)
            ? (bool) $validated['is_a_la_carte']
            : $menu->is_a_la_carte;

        $menuTypeId = $validated['menu_type_id'] ?? $menu->menu_type_id;

        if ($willBePublic && ! $menuTypeId) {
            throw ValidationException::withMessages([
                'menu_type_id' => ['Le type de menu est requis pour les menus publics.'],
            ]);
        }

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
        if (array_key_exists('menu_type_id', $validated)) {
            $menu->menu_type_id = $validated['menu_type_id'];
        }
        if (array_key_exists('priority', $validated)) {
            $menu->public_priority = $validated['priority'];
        }
        if (array_key_exists('service_type', $validated)) {
            $menu->service_type = $validated['service_type'];
        }
        if (array_key_exists('is_returnable', $validated)) {
            $menu->is_returnable = $validated['is_returnable'];
        }
        if (array_key_exists('price', $validated)) {
            $menu->price = $validated['price'];
        }
        $menu->save();

        if (array_key_exists('category_ids', $validated)) {
            $menu->categories()->sync($validated['category_ids']);
        }

        // Replace all items with the provided list
        $menu->items()->delete();

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

        return response()->json([
            'message' => 'Menu updated',
            'menu' => $menu->load('items.entity', 'categories', 'menuType'),
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
