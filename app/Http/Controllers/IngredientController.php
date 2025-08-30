<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Services\ImageService;
use App\Services\PerishableService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            // Fichier OU URL (au moins l’un des deux)
            'image' => 'nullable|image|max:2048|required_without:image_url',
            'image_url' => 'nullable|url|required_without:image',

            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

            'quantities' => 'required|array|min:1',
            'quantities.*.quantity' => 'required|numeric|min:0',
            'quantities.*.location_id' => 'required|exists:locations,id',

            'barcode' => 'nullable|string|max:255',
            // ⚠️ non nullable : requis au store
            'base_quantity' => 'required|numeric|min:0',
        ]);

        // Vérifier exclusivité : ne pas fournir "image" et "image_url" en même temps
        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        // Déterminer le chemin d'image si fourni (upload ou URL)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $imageService->store($request->file('image'), 'ingredients');
        } elseif ($request->filled('image_url')) {
            $imagePath = $imageService->storeFromUrl($request->input('image_url'), 'ingredients');
        }

        // Créer l’ingrédient
        $ingredient = Ingredient::create([
            'name' => $request->input('name'),
            'unit' => $request->input('unit'), // string accepté par le cast enum
            'company_id' => $user->company_id,
            'image_url' => $imagePath, // direct si présent
            'barcode' => $request->input('barcode'),
            'base_quantity' => $request->input('base_quantity'), // requis
            'category_id' => $request->input('category_id'),
        ]);

        // Quantités par location
        foreach ($request->input('quantities') as $quantityData) {
            $ingredient->locations()->syncWithoutDetaching([
                $quantityData['location_id'] => [
                    'quantity' => $quantityData['quantity'],
                ],
            ]);
        }

        return response()->json([
            'message' => 'Ingredient created successfully',
            'ingredient_id' => $ingredient->id,
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
            'quantities.*.location_id' => 'required|exists:locations,id',

            'barcode' => 'sometimes|nullable|string|max:255',
            // non nullable côté DB, mais en update on n'oblige pas si non fourni
            'base_quantity' => 'sometimes|numeric|min:0',
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
        $ingredient->save();

        if ($request->has('category_id')) {
            $ingredient->category_id = $request->input('category_id');
            $ingredient->save();
        }

        // Mettre à jour les quantités seulement si fournies
        if ($request->has('quantities')) {
            foreach ($request->input('quantities') as $quantityData) {
                $ingredient->locations()->syncWithoutDetaching([
                    $quantityData['location_id'] => [
                        'quantity' => $quantityData['quantity'],
                    ],
                ]);
            }
        }

        return response()->json([
            'message' => 'Ingredient updated successfully',
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
    public function addQuantity(Request $request, Ingredient $ingredient, StockService $stockService, PerishableService $perishableService): JsonResponse
    {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Ingredient not found',
            ], 404);
        }

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $locationId = (int) $validated['location_id'];
        $quantity = (float) $validated['quantity'];

        $stockService->add($ingredient, $locationId, $user->company_id, $quantity);
        $perishableService->add($ingredient->id, $locationId, $user->company_id, $quantity);

        return response()->json([
            'message' => 'Ingredient quantity updated successfully',
            'ingredient' => $ingredient->load('locations', 'category'),
        ], 200);
    }

    /**
     * Cas métier : Retrait de stock d'un ingrédient sur un emplacement.
     */
    public function removeQuantity(Request $request, Ingredient $ingredient, StockService $stockService, PerishableService $perishableService): JsonResponse
    {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Ingredient not found',
            ], 404);
        }

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $locationId = (int) $validated['location_id'];
        $quantity = (float) $validated['quantity'];

        try {
            $stockService->remove($ingredient, $locationId, $user->company_id, $quantity);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        $perishableService->remove($ingredient->id, $locationId, $user->company_id, $quantity);

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
        ]);

        try {
            $stockService->move(
                $ingredient,
                (int) $validated['from_location_id'],
                (int) $validated['to_location_id'],
                $user->company_id,
                (float) $validated['quantity']
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
