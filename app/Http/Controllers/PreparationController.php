<?php

namespace App\Http\Controllers;

use App\Models\Preparation;
use App\Models\PreparationEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreparationController extends Controller
{
    /**
     * Create a new preparation
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('preparations')->where('company_id', $user->company_id),
            ],
            'unit' => [
                'required',
                'string',
                'max:255',
            ],
            'entities' => [
                'required',
                'array',
                'min:2',
            ],
            'entities.*.id' => ['required', 'integer'],
            'entities.*.type' => ['required', 'string', 'in:ingredient,preparation'],
        ]);

        $validated['company_id'] = $user->company_id;

        $preparation = Preparation::create($validated);

        // Création des entités liées
        foreach ($validated['entities'] as $entity) {
            $entityClass = $entity['type'] === 'ingredient'
                ? \App\Models\Ingredient::class
                : \App\Models\Preparation::class;

            PreparationEntity::create([
                'preparation_id' => $preparation->id,
                'entity_id' => $entity['id'],
                'entity_type' => $entityClass,
            ]);
        }

        return response()->json([
            'message' => 'Preparation created successfully',
            'preparation' => $preparation->load('entities.entity'),
        ], 201);
    }

    /**
     * Update an existing preparation
     *
     * @param  int  $id
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('preparations')->where('company_id', $user->company_id)->ignore($id),
            ],
            'unit' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'entities' => [
                'sometimes',
                'array',
                'min:2',
            ],
            'entities.*.id' => ['required_with:entities', 'integer'],
            'entities.*.type' => ['required_with:entities', 'string', 'in:ingredient,preparation'],
        ]);

        $preparation->update($validated);

        // Si on veut mettre à jour les entités liées
        if (isset($validated['entities'])) {
            // On supprime les anciennes
            $preparation->entities()->delete();

            // On recrée les nouvelles
            foreach ($validated['entities'] as $entity) {
                $entityClass = $entity['type'] === 'ingredient'
                    ? \App\Models\Ingredient::class
                    : \App\Models\Preparation::class;

                PreparationEntity::create([
                    'preparation_id' => $preparation->id,
                    'entity_id' => $entity['id'],
                    'entity_type' => $entityClass,
                ]);
            }
        }

        return response()->json([
            'message' => 'Preparation updated successfully',
            'preparation' => $preparation->load('entities.entity'),
        ], 200);
    }
}
