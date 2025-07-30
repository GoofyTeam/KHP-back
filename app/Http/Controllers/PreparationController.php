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
     * On attend maintenant deux tableaux optionnels :
     * - 'entities_to_add'    : array d’entités à créer si elles n’existent pas encore
     * - 'entities_to_remove' : array d’entités à supprimer
     *
     * Règles métier pour update() :
     * - La préparation doit appartenir à la même société que l’utilisateur (404 sinon).
     * - 'name' et 'unit' restent facultatifs et validés comme avant.
     * - 'entities_to_add' et 'entities_to_remove' sont chacun :
     *     • facultatifs
     *     • tableau d’objets { id:int, type:'ingredient'|'preparation' }
     * - Si on fournit 'entities_to_remove', on supprime **seulement** ces liens.
     * - Si on fournit 'entities_to_add', on crée **seulement** les nouveaux liens qui n’existent pas.
     *
     * Succès : HTTP 200 + JSON { message, preparation (avec entités chargées) }.
     * Échec : HTTP 422 si validation, HTTP 404 si accès non autorisé.
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
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('preparations')
                ->where('company_id', $user->company_id)->ignore($id)],
            'unit' => ['sometimes', 'string', 'max:255'],
            'entities_to_add' => ['sometimes', 'array'],
            'entities_to_add.*.id' => ['required_with:entities_to_add', 'integer'],
            'entities_to_add.*.type' => ['required_with:entities_to_add', 'string', 'in:ingredient,preparation'],
            'entities_to_remove' => ['sometimes', 'array'],
            'entities_to_remove.*.id' => ['required_with:entities_to_remove', 'integer'],
            'entities_to_remove.*.type' => ['required_with:entities_to_remove', 'string', 'in:ingredient,preparation'],
        ]);

        // Mise à jour des champs standard
        $preparation->update($validated);

        // Suppressions demandées
        if (! empty($validated['entities_to_remove'] ?? [])) {
            foreach ($validated['entities_to_remove'] as $entity) {
                PreparationEntity::where('preparation_id', $preparation->id)
                    ->where('entity_id', $entity['id'])
                    ->delete();
            }
        }

        // Ajouts demandés
        if (! empty($validated['entities_to_add'] ?? [])) {
            // Récupère les IDs déjà présents
            $existing = $preparation->entities()
                ->pluck('entity_id')
                ->toArray();

            foreach ($validated['entities_to_add'] as $entity) {
                if (! in_array($entity['id'], $existing, true)) {
                    $entityClass = $entity['type'] === 'ingredient'
                        ? Ingredient::class
                        : Preparation::class;

                    PreparationEntity::create([
                        'preparation_id' => $preparation->id,
                        'entity_id' => $entity['id'],
                        'entity_type' => $entityClass,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Préparation mise à jour avec succès',
            'preparation' => $preparation->load('entities.entity'),
        ], 200);
    }

    /**
     * Supprime une préparation.
     *
     * Règles métier pour destroy() :
     * - La préparation doit appartenir à la même société que l’utilisateur (404 sinon).
     * - La suppression est en cascade, donc toutes les entités liées sont également supprimées.
     *
     * Succès : HTTP 204 sans contenu.
     * Échec : HTTP 404 si la préparation n'existe pas ou n'appartient pas à la société de l'utilisateur.
     *
     * @param  int  $id
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $preparation->delete();

        return response()->json(null, 204);
    }
}
