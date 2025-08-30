<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LocationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Crée une nouvelle catégorie avec des durées de conservation
     * obligatoires pour le réfrigérateur et le congélateur.
     *
     * Règles métier :
     * - 'name' : requis, unique pour l'entreprise de l'utilisateur.
     * - 'shelf_lives.fridge'  : requis, entier positif (heures).
     * - 'shelf_lives.freezer' : requis, entier positif (heures).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'shelf_lives' => ['required', 'array'],
            'shelf_lives.fridge' => ['required', 'integer', 'min:1'],
            'shelf_lives.freezer' => ['required', 'integer', 'min:1'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'company_id' => $user->company_id,
        ]);

        $freezer = LocationType::firstOrCreate(
            ['company_id' => $user->company_id, 'name' => 'Congélateur'],
            ['is_default' => true]
        );
        $fridge = LocationType::firstOrCreate(
            ['company_id' => $user->company_id, 'name' => 'Réfrigérateur'],
            ['is_default' => true]
        );

        $category->locationTypes()->attach([
            $fridge->id => ['shelf_life_hours' => $validated['shelf_lives']['fridge']],
            $freezer->id => ['shelf_life_hours' => $validated['shelf_lives']['freezer']],
        ]);

        return response()->json([
            'message' => 'Catégorie créée avec succès',
            'data' => $category->load('locationTypes'),
        ], 201);
    }

    /**
     * Met à jour une catégorie et ses durées de conservation.
     *
     * Règles métier :
     * - toute durée fournie peut être définie à null pour supprimer
     *   l'association existante ;
     * - les durées pour le réfrigérateur et le congélateur ne peuvent
     *   jamais être supprimées.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = Category::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories')->where(fn ($q) => $q->where('company_id', $user->company_id))->ignore($category->id),
            ],
            'shelf_lives' => ['sometimes', 'array'],
            'shelf_lives.fridge' => ['sometimes', 'required', 'integer', 'min:1'],
            'shelf_lives.freezer' => ['sometimes', 'required', 'integer', 'min:1'],
            'shelf_lives.*' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['name'])) {
            $category->name = $validated['name'];
            $category->save();
        }

        if (isset($validated['shelf_lives'])) {
            $freezer = LocationType::firstOrCreate(
                ['company_id' => $user->company_id, 'name' => 'Congélateur'],
                ['is_default' => true]
            );
            $fridge = LocationType::firstOrCreate(
                ['company_id' => $user->company_id, 'name' => 'Réfrigérateur'],
                ['is_default' => true]
            );

            $shelfLives = $validated['shelf_lives'];

            if (array_key_exists('fridge', $shelfLives)) {
                $category->locationTypes()->syncWithoutDetaching([
                    $fridge->id => ['shelf_life_hours' => $shelfLives['fridge']],
                ]);
            }

            if (array_key_exists('freezer', $shelfLives)) {
                $category->locationTypes()->syncWithoutDetaching([
                    $freezer->id => ['shelf_life_hours' => $shelfLives['freezer']],
                ]);
            }

            foreach ($shelfLives as $key => $hours) {
                if (in_array($key, ['fridge', 'freezer'])) {
                    continue;
                }
                if (! is_numeric($key)) {
                    continue;
                }
                $locationType = LocationType::forCompany()->findOrFail((int) $key);
                if ($hours === null) {
                    $category->locationTypes()->detach($locationType->id);
                } else {
                    $category->locationTypes()->syncWithoutDetaching([
                        $locationType->id => ['shelf_life_hours' => $hours],
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès',
            'data' => $category->load('locationTypes'),
        ]);
    }

    /**
     * Supprime une catégorie ainsi que ses durées de conservation associées.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = Category::where('company_id', $user->company_id)->findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Catégorie supprimée avec succès',
        ]);
    }
}
