<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * On attend maintenant deux tableaux optionnels et un tableau de quantités :
     * - 'entities_to_add'    : array d'entités à créer si elles n'existent pas encore
     * - 'entities_to_remove' : array d'entités à supprimer
     * - 'quantities'         : array de quantités par emplacement
     *
     * Règles métier pour update() :
     * - La préparation doit appartenir à la même société que l'utilisateur (404 sinon).
     * - 'name' et 'unit' restent facultatifs et validés comme avant.
     * - 'entities_to_add' et 'entities_to_remove' sont chacun :
     *     • facultatifs
     *     • tableau d'objets { id:int, type:'ingredient'|'preparation' }
     * - 'quantities' est un tableau d'objets { location_id:int, quantity:float }
     * - Si on fournit 'entities_to_remove', on supprime **seulement** ces liens.
     * - Si on fournit 'entities_to_add', on crée **seulement** les nouveaux liens qui n'existent pas.
     * - Si on fournit 'quantities', on met à jour ou ajoute les quantités pour les emplacements spécifiés.
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
            'quantities' => ['sometimes', 'array'],
            'quantities.*.quantity' => ['required_with:quantities', 'numeric', 'min:0'],
            'quantities.*.location_id' => ['required_with:quantities', 'exists:locations,id'],
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

        // Gestion des quantités par emplacement
        if (! empty($validated['quantities'] ?? [])) {
            foreach ($validated['quantities'] as $quantityData) {
                // Mise à jour ou ajout des quantités par emplacement
                $preparation->locations()->syncWithoutDetaching([
                    $quantityData['location_id'] => [
                        'quantity' => $quantityData['quantity'],
                    ],
                ]);
            }
        }

        return response()->json([
            'message' => 'Préparation mise à jour avec succès',
            'preparation' => $preparation->load('entities.entity', 'locations'),
        ], 200);
    }

    /**
     * Supprime une préparation.
     *
     * Règles métier pour destroy() :
     * - La préparation doit appartenir à la même société que l'utilisateur (404 sinon).
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

    /**
     * Prépare une quantité d'une préparation en utilisant ses composants.
     *
     * Cette fonction:
     * 1. Retire les quantités spécifiées des composants (ingrédients/préparations)
     * 2. Ajoute la quantité produite à l'emplacement de destination
     *
     * Pour chaque composant, on peut prélever des quantités depuis plusieurs emplacements.
     * Si la quantité disponible est insuffisante, l'opération échoue.
     * Les emplacements de type congélateur ne peuvent pas être utilisés.
     *
     * @param  int  $id
     */
    public function prepare(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Récupérer la préparation
        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        // Valider les données de requête
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'location_id' => ['required', 'exists:locations,id'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.entity_id' => ['required', 'integer'],
            'components.*.entity_type' => ['required', 'string', 'in:ingredient,preparation'],
            'components.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'components.*.sources' => ['required', 'array', 'min:1'],
            'components.*.sources.*.location_id' => ['required', 'exists:locations,id'],
            'components.*.sources.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Vérifier que l'emplacement de destination appartient à la même entreprise
        Location::where('id', $validated['location_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        // Trouver le type de localisation "Congélateur" pour cette entreprise
        $freezerType = LocationType::where('name', 'Congélateur')
            ->where(function ($query) use ($user) {
                $query->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            })
            ->first();

        // Utiliser une transaction pour garantir l'intégrité des données
        try {
            DB::beginTransaction();

            // Parcourir les composants
            foreach ($validated['components'] as $component) {
                $entityType = $component['entity_type'] === 'ingredient'
                    ? Ingredient::class
                    : Preparation::class;

                // Vérifier que le composant existe et appartient à l'entreprise
                $entity = $entityType::where('id', $component['entity_id'])
                    ->where('company_id', $user->company_id)
                    ->firstOrFail();

                // Vérifier que l'entité est bien un composant de la préparation
                $isComponent = $preparation->entities()
                    ->where('entity_id', $component['entity_id'])
                    ->where('entity_type', $entityType)
                    ->exists();

                if (! $isComponent) {
                    throw new \Exception("L'entité {$component['entity_id']} n'est pas un composant de cette préparation");
                }

                // Vérifier que la somme des quantités des sources correspond à la quantité requise
                $totalSourceQuantity = array_sum(array_column($component['sources'], 'quantity'));
                if (abs($totalSourceQuantity - $component['quantity']) > 0.001) { // Tolérance pour les erreurs d'arrondi
                    $entityName = $entity->name ?? "ID: {$component['entity_id']}";
                    throw new \Exception("La somme des quantités des sources ({$totalSourceQuantity}) ne correspond pas à la quantité requise ({$component['quantity']}) pour '{$entityName}'");
                }

                // Traiter chaque emplacement source pour ce composant
                foreach ($component['sources'] as $source) {
                    // Vérifier que l'emplacement source appartient à l'entreprise
                    $sourceLocation = Location::where('id', $source['location_id'])
                        ->where('company_id', $user->company_id)
                        ->firstOrFail();

                    // Vérifier que l'emplacement n'est pas un congélateur (si le type existe)
                    if ($freezerType && $sourceLocation->location_type_id === $freezerType->id) {
                        $entityName = $entity->name ?? "ID: {$component['entity_id']}";
                        throw new \Exception("Impossible d'utiliser un emplacement de type congélateur ('{$sourceLocation->name}') pour le composant '{$entityName}'");
                    }

                    // Vérifier le stock disponible
                    $locationEntity = $entity->locations()
                        ->wherePivot('location_id', $source['location_id'])
                        ->first();

                    /**
                     * @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot
                     */
                    $pivot = $locationEntity->pivot;

                    if (! $locationEntity || $pivot->quantity < $source['quantity']) {
                        $stockDispo = $locationEntity ? $pivot->quantity : 0;
                        $entityName = $entity->name ?? "ID: {$component['entity_id']}";
                        throw new \Exception("Stock insuffisant pour '{$entityName}' à l'emplacement '{$sourceLocation->name}' (disponible: {$stockDispo}, requis: {$source['quantity']})");
                    }

                    // Réduire la quantité du composant à cet emplacement
                    $entity->locations()->updateExistingPivot(
                        $source['location_id'],
                        ['quantity' => $pivot->quantity - $source['quantity']]
                    );
                }
            }

            // Ajouter la quantité préparée à l'emplacement de destination
            $existingQuantity = 0;
            $locationPrep = $preparation->locations()
                ->wherePivot('location_id', $validated['location_id'])
                ->first();

            if ($locationPrep) {
                /**
                 * @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot
                 */
                $pivot = $locationPrep->pivot;
                $existingQuantity = $pivot->quantity;
            }

            // Mettre à jour ou ajouter la quantité
            $preparation->locations()->syncWithoutDetaching([
                $validated['location_id'] => [
                    'quantity' => $existingQuantity + $validated['quantity'],
                ],
            ]);

            DB::commit();

            return response()->json([
                'message' => "Préparation de {$validated['quantity']} {$preparation->unit} de {$preparation->name} effectuée avec succès",
                'preparation' => $preparation->load('entities.entity', 'locations'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
