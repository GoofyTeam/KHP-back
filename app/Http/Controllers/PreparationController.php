<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Services\PerishableService;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * Cas métier : Création d'une préparation
     *
     * Use cases :
     * - Définir une nouvelle recette composée d'ingrédients et/ou de sous-préparations
     * - Associer une image illustrant la préparation
     * - Catégoriser la préparation pour la gestion des stocks
     *
     * Règles métier :
     * - 'name' unique par entreprise
     * - Au moins deux entités doivent composer la préparation
     * - Image et image_url sont mutuellement exclusifs
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, ImageService $imageService): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('preparations')->where(function ($q) use ($user) {
                    return $q->where('company_id', $user->company_id);
                }),
            ],
            'unit' => ['required', 'string', Rule::in(MeasurementUnit::values())],

            // Image (upload OU URL) - optionnels
            'image' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'image_url' => ['sometimes', 'nullable', 'url'],

            'entities' => ['required', 'array', 'min:2'],
            'entities.*.id' => ['required', 'integer'],
            'entities.*.type' => ['required', 'string', 'in:ingredient,preparation'],

            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        // Exclusivité XOR image/image_url
        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        // Traitement image (upload ou URL distante)
        $storedPath = null;
        if ($request->hasFile('image')) {
            $storedPath = $imageService->store($request->file('image'), 'preparations');
        } elseif ($request->filled('image_url')) {
            // Doit valider le MIME/taille côté service (comme pour Ingredient)
            $storedPath = $imageService->storeFromUrl($request->input('image_url'), 'preparations');
        }

        // Liaison à la société de l'utilisateur
        $data = [
            'company_id' => $user->company_id,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'image_url' => $storedPath, // peut rester null
            'category_id' => $validated['category_id'],
        ];

        $preparation = Preparation::create($data);

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
            'preparation' => $preparation->load('entities.entity', 'category'),
        ], 201);
    }

    /**
     * Cas métier : Mise à jour d'une préparation
     *
     * Use cases :
     * - Ajouter ou retirer des composants de la recette
     * - Ajuster les quantités disponibles par emplacement
     * - Modifier l'image ou la catégorie associée
     *
     * @param int $id
     */
    public function update(Request $request, $id, ImageService $imageService): JsonResponse
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
                Rule::unique('preparations')
                    ->where(function ($q) use ($user) {
                        return $q->where('company_id', $user->company_id);
                    })
                    ->ignore($id),
            ],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],

            // Image (upload OU URL) - optionnels
            'image' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'image_url' => ['sometimes', 'nullable', 'url'],

            'entities_to_add' => ['sometimes', 'array'],
            'entities_to_add.*.id' => ['required_with:entities_to_add', 'integer'],
            'entities_to_add.*.type' => ['required_with:entities_to_add', 'string', 'in:ingredient,preparation'],

            'entities_to_remove' => ['sometimes', 'array'],
            'entities_to_remove.*.id' => ['required_with:entities_to_remove', 'integer'],
            'entities_to_remove.*.type' => ['required_with:entities_to_remove', 'string', 'in:ingredient,preparation'],

            'quantities' => ['sometimes', 'array'],
            'quantities.*.quantity' => ['required_with:quantities', 'numeric', 'min:0'],
            'quantities.*.location_id' => ['required_with:quantities', 'exists:locations,id'],

            'category_id' => [
                'sometimes',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        // Exclusivité XOR image/image_url
        if ($request->hasFile('image') && $request->filled('image_url')) {
            throw ValidationException::withMessages([
                'image' => 'Ne fournissez pas "image" et "image_url" en même temps.',
                'image_url' => 'Ne fournissez pas "image" et "image_url" en même temps.',
            ]);
        }

        // Mise à jour des champs standard (name/unit)
        if (array_key_exists('name', $validated)) {
            $preparation->name = $validated['name'];
        }
        if (array_key_exists('unit', $validated)) {
            $preparation->unit = $validated['unit'];
        }

        // MAJ de l'image (upload ou URL distante)
        if ($request->hasFile('image')) {
            $preparation->image_url = $imageService->store($request->file('image'), 'preparations');
        } elseif ($request->filled('image_url')) {
            $preparation->image_url = $imageService->storeFromUrl($request->input('image_url'), 'preparations');
        }
        $preparation->save();

        // Suppressions demandées
        if (! empty($validated['entities_to_remove'] ?? [])) {
            foreach ($validated['entities_to_remove'] as $entity) {
                $entityClass = $entity['type'] === 'ingredient' ? Ingredient::class : Preparation::class;
                PreparationEntity::where('preparation_id', $preparation->id)
                    ->where('entity_id', $entity['id'])
                    ->where('entity_type', $entityClass) // précision du type
                    ->delete();
            }
        }

        // Ajouts demandés
        if (! empty($validated['entities_to_add'] ?? [])) {
            // Récupère les couples (id,type) déjà présents
            $existing = $preparation->entities()
                ->select('entity_id', 'entity_type')
                ->get()
                ->map(fn ($e) => $e['entity_type'].'#'.$e['entity_id'])
                ->toArray();

            foreach ($validated['entities_to_add'] as $entity) {
                $entityClass = $entity['type'] === 'ingredient' ? Ingredient::class : Preparation::class;
                $key = $entityClass.'#'.$entity['id'];

                if (! in_array($key, $existing, true)) {
                    PreparationEntity::create([
                        'preparation_id' => $preparation->id,
                        'entity_id' => $entity['id'],
                        'entity_type' => $entityClass,
                    ]);
                }
            }
        }

        if (isset($validated['category_id'])) {
            $preparation->category_id = $validated['category_id'];
            $preparation->save();
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
            'preparation' => $preparation->load('entities.entity', 'locations', 'category'),
        ], 200);
    }

    /**
     * Cas métier : Suppression d'une préparation
     *
     * Use cases :
     * - Retirer une recette qui n'est plus utilisée
     * - Corriger une préparation créée par erreur
     *
     * @param int $id
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
     * Cas métier : Réalisation d'une préparation
     *
     * Use cases :
     * - Transformer des ingrédients en préparation finale
     * - Déduire automatiquement les stocks utilisés
     * - Augmenter la quantité disponible de la préparation produite
     *
     * Les sources peuvent provenir de plusieurs emplacements et doivent respecter
     * la disponibilité des stocks.
     *
     * @param int $id
     */
    public function prepare(Request $request, $id, PerishableService $perishableService): JsonResponse
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
                    $pivot = $locationEntity->pivot ?? null;

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

                    if ($entity instanceof Ingredient) {
                        $perishableService->remove($entity->id, $source['location_id'], $user->company_id, $source['quantity']);
                    }
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
                'message' => "Préparation de {$validated['quantity']} {$preparation->unit->value} de {$preparation->name} effectuée avec succès",
                'preparation' => $preparation->load('entities.entity', 'locations', 'category'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cas métier : Ajustement du stock d'une préparation
     *
     * Use cases :
     * - Correction manuelle après inventaire
     * - Production ou retrait hors processus standard
     */
    public function adjustQuantity(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric'],
        ]);

        $location = Location::where('id', $validated['location_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $currentQuantity = (float) ($preparation->locations()->find($location->id)?->pivot->quantity ?? 0);
        $adjustment = (float) $validated['quantity'];
        $newQuantity = $currentQuantity + $adjustment;

        if ($newQuantity < 0) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        $preparation->locations()->syncWithoutDetaching([
            $location->id => ['quantity' => $newQuantity],
        ]);

        return response()->json([
            'message' => 'Quantité de la préparation mise à jour avec succès',
            'preparation' => $preparation->load('entities.entity', 'locations', 'category'),
        ], 200);
    }
}
