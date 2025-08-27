<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Location;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IngredientController extends Controller
{
    /**
     * Store a newly created resource in storage.
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

            'categories' => 'required|array|min:1',
            'categories.*' => 'string|max:255',

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

        // Préparer les catégories
        $categories = collect($request->input('categories'))
            ->map(function ($categoryName) use ($user) {
                $formattedName = ucfirst($categoryName);

                return Category::firstOrCreate([
                    'name' => $formattedName,
                    'company_id' => $user->company_id,
                ]);
            });

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
        ]);

        // Lier les catégories
        $ingredient->categories()->attach($categories->pluck('id'));

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
     * Update the specified resource in storage.
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

            'categories' => 'sometimes|array|min:0',
            'categories.*' => 'string|max:255',

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

        // Mettre à jour les catégories seulement si fournies
        if ($request->has('categories')) {
            $categories = collect($request->input('categories'))
                ->map(function ($categoryName) use ($user) {
                    return Category::firstOrCreate([
                        'name' => ucfirst($categoryName),
                        'company_id' => $user->company_id,
                    ]);
                });
            $ingredient->categories()->sync($categories->pluck('id'));
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
     * Remove the specified resource from storage.
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
     * Adjust the quantity of an ingredient for a specific location.
     */
    public function adjustQuantity(Request $request, Ingredient $ingredient): JsonResponse
    {
        $user = $request->user();

        if ($ingredient->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Ingredient not found',
            ], 404);
        }

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric'],
        ]);

        $location = Location::where('id', $validated['location_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $currentQuantity = (float) ($ingredient->locations()->find($location->id)?->pivot->quantity ?? 0);
        $adjustment = (float) $validated['quantity'];
        $newQuantity = $currentQuantity + $adjustment;

        if ($newQuantity < 0) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        $ingredient->locations()->syncWithoutDetaching([
            $location->id => ['quantity' => $newQuantity],
        ]);

        return response()->json([
            'message' => 'Ingredient quantity updated successfully',
            'ingredient' => $ingredient->load('locations', 'categories'),
        ], 200);
    }
}
