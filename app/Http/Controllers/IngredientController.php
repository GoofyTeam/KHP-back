<?php

namespace App\Http\Controllers;

use App\Enums\Allergen;
use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Services\ImageService;
use App\Services\PerishableService;
use App\Services\StockService;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IngredientController extends Controller
{
    /**
     * Cas métier : Création d'un nouvel ingrédient
     *
     * Use cases :
     * - Ajouter un ingrédient au catalogue de l'entreprise
     * - Référencer un produit avec sa catégorie et son unité
     * - Initialiser des stocks sur un ou plusieurs emplacements
     */
    public function store(Request $request, ImageService $imageService)
    {
        $user = auth()->user();

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ingredients')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            // Valide contre les valeurs de l'enum
            'unit' => ['required', 'string', 'max:50', Rule::in(MeasurementUnit::values())],

            // Fichier OU URL (optionnels mais mutuellement exclusifs)
            'image' => 'nullable|image|max:2048',
            'image_url' => 'nullable|url',

            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

            'quantities' => 'required|array|min:1',
            'quantities.*.quantity' => 'required|numeric|min:0',
            'quantities.*.location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

            'barcode' => 'nullable|string|max:255',
            // ⚠️ non nullable : requis au store
            'base_quantity' => 'required|numeric|min:0',
            'base_unit' => ['required', 'string', 'max:50', Rule::in(MeasurementUnit::values())],
            'allergens' => 'sometimes|array',
            'allergens.*' => Rule::in(Allergen::values()),
        ]);

        // Vérifier exclusivité : ne pas fournir "image" et "image_url" en même temps
        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        // Déterminer le chemin d'image (upload, URL ou placeholder)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $imageService->store($request->file('image'), 'ingredients');
        } elseif ($request->filled('image_url')) {
            $imagePath = $imageService->storeFromUrl($request->input('image_url'), 'ingredients');
        } else {
            $imagePath = $imageService->storePlaceholder();
        }

        // Créer l’ingrédient
        $ingredient = Ingredient::create([
            'name' => $request->input('name'),
            'unit' => $request->input('unit'), // string accepté par le cast enum
            'company_id' => $user->company_id,
            'image_url' => $imagePath, // direct si présent
            'barcode' => $request->input('barcode'),
            'base_quantity' => $request->input('base_quantity'), // requis
            'base_unit' => $request->input('base_unit'),
            'category_id' => $request->input('category_id'),
            'allergens' => $request->input('allergens', []),
        ]);

        // Quantités par location
        foreach ($request->input('quantities') as $i => $quantityData) {
            $locationId = $quantityData['location_id'];

            $location = Location::where('id', $locationId)
                ->where('company_id', $user->company_id)
                ->first();

            if (! $location) {
                throw ValidationException::withMessages([
                    "quantities.$i.location_id" => 'Invalid location.',
                ]);
            }

            $ingredient->locations()->syncWithoutDetaching([
                $locationId => [
                    'quantity' => $quantityData['quantity'],
                ],
            ]);

            $ingredient->recordStockMovement(
                $location,
                0,
                (float) $quantityData['quantity'],
                'Initial Quantity Set'
            );
        }

        return response()->json([
            'message' => 'Ingredient created successfully',
            'ingredient_id' => $ingredient->id,
        ], 201);
    }

    /**
     * Cas métier : Création de plusieurs ingrédients en une seule requête.
     *
     * Cette méthode applique les mêmes validations et logique métier que la
     * méthode store() mais pour un tableau d'ingrédients. Chaque entrée doit
     * respecter les mêmes règles (image ou image_url, quantités, etc.).
     */
    public function bulkStore(Request $request, ImageService $imageService)
    {
        $user = auth()->user();

        $request->validate([
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ingredients')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'ingredients.*.unit' => ['required', 'string', 'max:50', Rule::in(MeasurementUnit::values())],
            'ingredients.*.image' => 'nullable|image|max:2048',
            'ingredients.*.image_url' => 'nullable|url',
            'ingredients.*.category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'ingredients.*.quantities' => 'required|array|min:1',
            'ingredients.*.quantities.*.quantity' => 'required|numeric|min:0',
            'ingredients.*.quantities.*.location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'ingredients.*.barcode' => 'nullable|string|max:255',
            'ingredients.*.base_quantity' => 'required|numeric|min:0',
            'ingredients.*.base_unit' => ['required', 'string', 'max:50', Rule::in(MeasurementUnit::values())],
            'ingredients.*.allergens' => 'sometimes|array',
            'ingredients.*.allergens.*' => Rule::in(Allergen::values()),
        ]);

        // Détecte les noms dupliqués dans le même payload pour éviter une violation de contrainte unique
        $names = array_map(fn ($ing) => mb_strtolower($ing['name']), $request->input('ingredients'));
        $duplicates = array_keys(array_filter(array_count_values($names), fn ($count) => $count > 1));
        if ($duplicates) {
            $errors = [];
            foreach ($duplicates as $dup) {
                foreach ($names as $index => $name) {
                    if ($name === $dup) {
                        $errors["ingredients.$index.name"] = 'Duplicate ingredient name in payload.';
                    }
                }
            }
            throw ValidationException::withMessages($errors);
        }

        $createdIds = DB::transaction(function () use ($request, $user, $imageService) {
            $ids = [];

            foreach ($request->input('ingredients') as $index => $data) {
                // Vérifier exclusivité image / image_url
                if (isset($data['image']) && ! empty($data['image']) && ! empty($data['image_url'])) {
                    throw ValidationException::withMessages([
                        "ingredients.$index.image" => 'Ne fournissez pas "image" et "image_url" en même temps.',
                        "ingredients.$index.image_url" => 'Ne fournissez pas "image" et "image_url" en même temps.',
                    ]);
                }

                $imagePath = null;
                if (! empty($data['image'])) {
                    $imagePath = $imageService->store($data['image'], 'ingredients');
                } elseif (! empty($data['image_url'])) {
                    $imagePath = $imageService->storeFromUrl($data['image_url'], 'ingredients');
                } else {
                    $imagePath = $imageService->storePlaceholder();
                }

                $ingredient = Ingredient::create([
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'company_id' => $user->company_id,
                    'image_url' => $imagePath,
                    'barcode' => $data['barcode'] ?? null,
                    'base_quantity' => $data['base_quantity'],
                    'base_unit' => $data['base_unit'],
                    'category_id' => $data['category_id'],
                    'allergens' => $data['allergens'] ?? [],
                ]);

                foreach ($data['quantities'] as $i => $quantityData) {
                    $locationId = $quantityData['location_id'];

                    $location = Location::where('id', $locationId)
                        ->where('company_id', $user->company_id)
                        ->first();

                    if (! $location) {
                        throw ValidationException::withMessages([
                            "ingredients.$index.quantities.$i.location_id" => 'Invalid location.',
                        ]);
                    }

                    $ingredient->locations()->syncWithoutDetaching([
                        $locationId => [
                            'quantity' => $quantityData['quantity'],
                        ],
                    ]);

                    $ingredient->recordStockMovement(
                        $location,
                        0,
                        (float) $quantityData['quantity'],
                        'Initial Quantity Set'
                    );
                }

                $ids[] = $ingredient->id;
            }

            return $ids;
        });

        return response()->json([
            'message' => 'Ingredients created successfully',
            'ingredient_ids' => $createdIds,
        ], 201);
    }

    /**
     * Cas métier : Mise à jour d'un ingrédient existant
     *
     * Use cases :
     * - Modifier le nom ou l'unité d'un ingrédient
     * - Changer l'image associée
     * - Recatégoriser un produit
     */
    public function update(Request $request, Ingredient $ingredient, ImageService $imageService)
    {
        $user = auth()->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Unauthorized action',
            ], 403);
        }

        $request->validate([
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('ingredients')->ignore($ingredient->id)->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            // Accepte une nouvelle unit si fournie, et valide contre l'enum
            'unit' => ['sometimes', 'string', 'max:50', Rule::in(MeasurementUnit::values())],

            // Autoriser mise à jour par fichier OU URL (mais pas les deux)
            'image' => 'sometimes|nullable|image|max:2048',
            'image_url' => 'sometimes|nullable|url',

            'category_id' => [
                'sometimes',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

            'quantities' => 'sometimes|array|min:0',
            'quantities.*.quantity' => 'required|numeric|min:0',
            'quantities.*.location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

            'barcode' => 'sometimes|nullable|string|max:255',
            // non nullable côté DB, mais en update on n'oblige pas si non fourni
            'base_quantity' => 'sometimes|numeric|min:0',
            'base_unit' => ['sometimes', 'string', 'max:50', Rule::in(MeasurementUnit::values())],
            'allergens' => 'sometimes|array',
            'allergens.*' => Rule::in(Allergen::values()),
        ]);

        // Vérifier exclusivité : ne pas fournir "image" et "image_url" en même temps
        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        // Image (upload ou URL)
        if ($request->hasFile('image')) {
            $ingredient->image_url = $imageService->store($request->file('image'), 'ingredients');
        } elseif ($request->filled('image_url')) {
            $ingredient->image_url = $imageService->storeFromUrl($request->input('image_url'), 'ingredients');
        }

        // Champs simples
        if ($request->has('name')) {
            $ingredient->name = $request->input('name');
        }
        if ($request->has('unit')) {
            $ingredient->unit = $request->input('unit');
        }
        if ($request->has('barcode')) {
            $ingredient->barcode = $request->input('barcode');
        }
        if ($request->has('base_quantity')) {
            $ingredient->base_quantity = $request->input('base_quantity');
        }
        if ($request->has('base_unit')) {
            $ingredient->base_unit = $request->input('base_unit');
        }
        if ($request->has('allergens')) {
            $ingredient->allergens = $request->input('allergens');
        }
        $ingredient->save();

        if ($request->has('category_id')) {
            $ingredient->category_id = $request->input('category_id');
            $ingredient->save();
        }

        // Mettre à jour les quantités seulement si fournies
        if ($request->has('quantities')) {
            foreach ($request->input('quantities') as $i => $quantityData) {
                $locationId = $quantityData['location_id'];

                $location = Location::where('id', $locationId)
                    ->where('company_id', $user->company_id)
                    ->first();

                if (! $location) {
                    throw ValidationException::withMessages([
                        "quantities.$i.location_id" => 'Invalid location.',
                    ]);
                }

                $existing = $ingredient->locations()->where('locations.id', $locationId)->first();
                /** @var (\Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float})|null $pivot */
                $pivot = $existing?->pivot;
                $before = $pivot ? (float) $pivot->quantity : 0.0;

                $ingredient->locations()->syncWithoutDetaching([
                    $locationId => [
                        'quantity' => $quantityData['quantity'],
                    ],
                ]);

                $ingredient->recordStockMovement(
                    $location,
                    $before,
                    (float) $quantityData['quantity'],
                    'Quantity Manually Adjusted'
                );
            }
        }

        return response()->json([
            'message' => 'Ingredient updated successfully',
        ], 200);
    }

    public function updateThreshold(Request $request, Ingredient $ingredient): JsonResponse
    {
        $user = auth()->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Unauthorized action',
            ], 403);
        }

        $validated = $request->validate([
            'threshold' => ['present', 'nullable', 'numeric', 'min:0'],
        ]);

        $ingredient->threshold = $validated['threshold'];
        $ingredient->save();

        return response()->json([
            'message' => 'Ingredient threshold updated successfully',
            'threshold' => $ingredient->threshold,
        ], 200);
    }

    /**
     * Réinitialise le seuil d'un ingrédient à null.
     */
    public function resetThreshold(Request $request, Ingredient $ingredient): JsonResponse
    {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Unauthorized action',
            ], 403);
        }

        $ingredient->threshold = null;
        $ingredient->save();

        return response()->json([
            'message' => 'Ingredient threshold reset successfully',
        ], 200);
    }

    /**
     * Cas métier : Suppression d'un ingrédient
     *
     * Use cases :
     * - Retirer un ingrédient obsolète du catalogue
     * - Corriger une création erronée
     */
    public function destroy(Ingredient $ingredient)
    {
        if ($ingredient->company_id !== auth()->user()->company_id) {
            return response()->json([
                'message' => 'Unauthorized action',
            ], 403);
        }

        $ingredient->delete();

        return response()->json([
            'message' => 'Ingredient deleted successfully',
        ], 200);
    }

    /**
     * Cas métier : Ajout de stock d'un ingrédient sur un emplacement.
     */
    public function addQuantity(
        Request $request,
        Ingredient $ingredient,
        StockService $stockService,
        PerishableService $perishableService,
        UnitConversionService $unitConversionService
    ): JsonResponse {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Ingredient not found',
            ], 404);
        }

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        $locationId = (int) $validated['location_id'];
        $quantity = (float) $validated['quantity'];
        $unit = isset($validated['unit']) ? MeasurementUnit::from($validated['unit']) : null;

        $stockService->add($ingredient, $locationId, $user->company_id, $quantity, null, $unit);

        $converted = $unit && $unit !== $ingredient->unit
            ? $unitConversionService->convert($quantity, $unit, $ingredient->unit)
            : $quantity;

        $perishableService->add($ingredient->id, $locationId, $user->company_id, $converted);

        return response()->json([
            'message' => 'Ingredient quantity updated successfully',
            'ingredient' => $ingredient->load('locations', 'category'),
        ], 200);
    }

    /**
     * Cas métier : Retrait de stock d'un ingrédient sur un emplacement.
     */
    public function removeQuantity(
        Request $request,
        Ingredient $ingredient,
        StockService $stockService,
        PerishableService $perishableService,
        UnitConversionService $unitConversionService
    ): JsonResponse {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Ingredient not found',
            ], 404);
        }

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        $locationId = (int) $validated['location_id'];
        $quantity = (float) $validated['quantity'];
        $unit = isset($validated['unit']) ? MeasurementUnit::from($validated['unit']) : null;

        try {
            $stockService->remove($ingredient, $locationId, $user->company_id, $quantity, null, $unit);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        $converted = $unit && $unit !== $ingredient->unit
            ? $unitConversionService->convert($quantity, $unit, $ingredient->unit)
            : $quantity;

        $perishableService->remove($ingredient->id, $locationId, $user->company_id, $converted);

        return response()->json([
            'message' => 'Ingredient quantity updated successfully',
            'ingredient' => $ingredient->load('locations', 'category'),
        ], 200);
    }

    /**
     * Cas métier : Déplacement de stock d'un ingrédient entre deux emplacements.
     */
    public function moveQuantity(Request $request, Ingredient $ingredient, StockService $stockService): JsonResponse
    {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Ingredient not found',
            ], 404);
        }

        $validated = $request->validate([
            'from_location_id' => ['required', 'integer', 'exists:locations,id'],
            'to_location_id' => ['required', 'integer', 'different:from_location_id', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        $unit = isset($validated['unit']) ? MeasurementUnit::from($validated['unit']) : null;

        try {
            $stockService->move(
                $ingredient,
                (int) $validated['from_location_id'],
                (int) $validated['to_location_id'],
                $user->company_id,
                (float) $validated['quantity'],
                $unit
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        return response()->json([
            'message' => 'Ingredient quantity moved successfully',
            'ingredient' => $ingredient->load('locations', 'category'),
        ], 200);
    }
}
