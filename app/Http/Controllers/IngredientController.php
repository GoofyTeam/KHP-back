<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
                Rule::unique('ingredients')->where(function ($query) use ($user) { // Ajout du "use ($user)"
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'unit' => 'required|string|max:50',
            'image' => 'nullable|image|max:2048',
            'categories' => 'required|array|min:1',
            'categories.*' => 'string|max:255',
            'quantities' => 'required|array|min:1',
            'quantities.*.quantity' => 'required|numeric|min:0',
            'quantities.*.location_id' => 'required|exists:locations,id',
        ]);

        $categories = collect($request->input('categories'))->map(function ($categoryName) use ($user) {
            $formattedName = ucfirst($categoryName);

            return Category::firstOrCreate(['name' => $formattedName, 'company_id' => $user->company_id]);
        });

        $ingredient = Ingredient::create([
            'name' => $request->input('name'),
            'unit' => $request->input('unit'),
            'company_id' => $user->company_id,
            // 'image_url' => $imageService->store(
            //     $request->file('image'),
            //     'ingredients'
            // ),
        ]);

        if ($request->hasFile('image')) {
            $ingredient->image_url = $imageService->store(
                $request->file('image'),
                'ingredients'
            );
            $ingredient->save();
        }

        $ingredient->categories()->attach($categories->pluck('id'));

        // pour chaque location, on cree ou met a jour la quantite dans la table ingredient_location
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
            'unit' => 'sometimes|string|max:50',
            'image' => 'nullable|image|max:2048',
            'categories' => 'sometimes|array|min:0',
            'categories.*' => 'string|max:255',
            'quantities' => 'sometimes|array|min:0',
            'quantities.*.quantity' => 'required|numeric|min:0',
            'quantities.*.location_id' => 'required|exists:locations,id',
        ]);

        if ($request->hasFile('image')) {
            $ingredient->image_url = $imageService->store(
                $request->file('image'),
                'ingredients'
            );
        }

        $ingredient->name = $request->input('name', $ingredient->name);
        $ingredient->unit = $request->input('unit', $ingredient->unit);
        $ingredient->save();

        // Update categories
        $categories = collect($request->input('categories'))->map(function ($categoryName) use ($user) {
            return Category::firstOrCreate(['name' => ucfirst($categoryName), 'company_id' => $user->company_id]);
        });

        $ingredient->categories()->sync($categories->pluck('id'));

        // Update quantities
        foreach ($request->input('quantities') as $quantityData) {
            $ingredient->locations()->syncWithoutDetaching([
                $quantityData['location_id'] => [
                    'quantity' => $quantityData['quantity'],
                ],
            ]);
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
}
