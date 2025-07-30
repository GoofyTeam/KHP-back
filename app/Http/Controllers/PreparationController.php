<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PreparationController
 *
 * Ce contrôleur gère la création et la mise à jour des préparations,
 * ainsi que la gestion des entités associées (ingrédients ou sous-préparations).
 *
 * Les règles métier sont documentées en détail pour chaque méthode.
 */
class PreparationController extends Controller
{
    /**
     * Crée une nouvelle préparation.
     *
     * Règles métier pour la méthode store() :
     * - 'name' : obligatoire, chaîne de caractères, max 255, unique par société.
     * - 'unit' : obligatoire, chaîne de caractères, max 255.
     * - 'entities' : obligatoire, tableau, au moins 2 éléments.
     *     • entities.*.id : entier requis.
     *     • entities.*.type : doit être 'ingredient' ou 'preparation'.
     * - La préparation est automatiquement liée à la société de l'utilisateur.
     * - Pour chaque entité fournie, un enregistrement PreparationEntity est créé.
     *
     * Succès : HTTP 201 + JSON [ 'message', 'preparation' avec entités chargées ].
     * Échec : HTTP 422 + détails des erreurs de validation.
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
                Rule::unique('preparations')->where('company_id', $user->company_id),
            ],
            'unit' => ['required', 'string', 'max:255'],
            'entities' => ['required', 'array', 'min:2'],
            'entities.*.id' => ['required', 'integer'],
            'entities.*.type' => ['required', 'string', 'in:ingredient,preparation'],
        ]);

        // Liaison à la société de l'utilisateur
        $validated['company_id'] = $user->company_id;
        $preparation = Preparation::create($validated);

        // Création des liens vers les entités
        foreach ($validated['entities'] as $entity) {
            $entityClass = $entity['type'] === 'ingredient' ? Ingredient::class : Preparation::class;
            PreparationEntity::create([
                'preparation_id' => $preparation->id,
                'entity_id' => $entity['id'],
                'entity_type' => $entityClass,
            ]);
        }

        return response()->json([
            'message' => 'Préparation créée avec succès',
            'preparation' => $preparation->load('entities.entity'),
        ], 201);
    }

    /**
     * Met à jour une préparation existante.
     *
     * Règles métier pour la méthode update() :
     * - La préparation doit appartenir à la société de l'utilisateur (404 sinon).
     * - 'name' : optionnel, si présent doit être chaîne, max 255, unique par société en ignorant l'ID.
     * - 'unit' : optionnel, si présent doit être chaîne, max 255.
     * - 'entities' : optionnel.
     *     • Si absent : on ne modifie pas les liens existants.
     *     • Si présent :
     *         - entities.*.id : entier requis.
     *         - entities.*.type : 'ingredient' ou 'preparation'.
     *         - suppression des anciens liens PreparationEntity.
     *         - création de nouveaux liens PreparationEntity.
     *
     * Succès : HTTP 200 + JSON [ 'message', 'preparation' avec entités chargées ].
     * Échec : HTTP 422 pour validation, HTTP 404 si non autorisé.
     *
     * @param  int  $id
     *
     * @throws \Illuminate\Validation\ValidationException
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
            'unit' => ['sometimes', 'string', 'max:255'],
            'entities' => ['sometimes', 'array'],
            'entities.*.id' => ['required_with:entities', 'integer'],
            'entities.*.type' => ['required_with:entities', 'string', 'in:ingredient,preparation'],
        ]);

        // Mise à jour des champs name et/ou unit
        $preparation->update($validated);

        // Si des entités sont fournies, on remplace les liens
        if ($request->has('entities')) {
            $preparation->entities()->delete();
            foreach ($validated['entities'] as $entity) {
                $entityClass = $entity['type'] === 'ingredient' ? Ingredient::class : Preparation::class;
                PreparationEntity::create([
                    'preparation_id' => $preparation->id,
                    'entity_id' => $entity['id'],
                    'entity_type' => $entityClass,
                ]);
            }
        }

        return response()->json([
            'message' => 'Préparation mise à jour avec succès',
            'preparation' => $preparation->load('entities.entity'),
        ], 200);
    }
}
