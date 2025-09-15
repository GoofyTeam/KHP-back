<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Services\ImageService;
use App\Services\PerishableService;
use App\Services\StockService;
use App\Services\UnitConversionService;
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
            'base_quantity' => ['required', 'numeric', 'min:0'],
            'base_unit' => ['required', 'string', Rule::in(MeasurementUnit::values())],

            // Image (upload OU URL) - optionnels
            'image' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'image_url' => ['sometimes', 'nullable', 'url'],

            'entities' => ['required', 'array', 'min:1'],
            'entities.*.id' => ['required', 'integer'],
            'entities.*.type' => ['required', 'string', 'in:ingredient,preparation'],
            'entities.*.quantity' => ['required', 'numeric', 'min:0'],
            'entities.*.unit' => ['required', 'string', Rule::in(MeasurementUnit::values())],
            'entities.*.location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

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
            'base_quantity' => $validated['base_quantity'],
            'base_unit' => $validated['base_unit'],
            'image_url' => $storedPath, // peut rester null
            'category_id' => $validated['category_id'],
        ];

        $preparation = Preparation::create($data);

        // Création des liens vers les entités
        foreach ($validated['entities'] as $entity) {
            $entityClass = $entity['type'] === 'ingredient' ? Ingredient::class : Preparation::class;

            // Vérifier que l'entité appartient à la même entreprise
            $entityClass::where('id', $entity['id'])
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            // Vérifier la localisation
            Location::where('id', $entity['location_id'])
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            PreparationEntity::create([
                'preparation_id' => $preparation->id,
                'entity_id' => $entity['id'],
                'entity_type' => $entityClass,
                'location_id' => $entity['location_id'],
                'quantity' => $entity['quantity'],
                'unit' => $entity['unit'],
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
     * @param  int  $id
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
            'quantities.*.location_id' => [
                'required_with:quantities',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],

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
                // Vérifier que l'entité appartient à la même entreprise
                $entityClass::where('id', $entity['id'])
                    ->where('company_id', $user->company_id)
                    ->firstOrFail();

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

                // Vérifier que l'entité appartient à la même entreprise
                $entityClass::where('id', $entity['id'])
                    ->where('company_id', $user->company_id)
                    ->firstOrFail();

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
                // Vérifier que l'emplacement appartient à la même entreprise
                $location = Location::where('id', $quantityData['location_id'])
                    ->where('company_id', $user->company_id)
                    ->firstOrFail();

                $existing = $preparation->locations()->where('locations.id', $location->id)->first();
                /** @var (\Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float})|null $pivot */
                $pivot = $existing?->pivot;
                $before = $pivot ? (float) $pivot->quantity : 0.0;

                // Mise à jour ou ajout des quantités par emplacement
                $preparation->locations()->syncWithoutDetaching([
                    $location->id => [
                        'quantity' => $quantityData['quantity'],
                    ],
                ]);

                $preparation->recordStockMovement(
                    $location,
                    $before,
                    (float) $quantityData['quantity'],
                    'Quantity Manually Adjusted'
                );
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
     * @param  int  $id
     */
    public function prepare(Request $request, $id, PerishableService $perishableService, UnitConversionService $unitConversionService): JsonResponse
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
            'overrides' => ['sometimes', 'array'],
            'overrides.*.id' => ['required_with:overrides', 'integer'],
            'overrides.*.type' => ['required_with:overrides', 'string', 'in:ingredient,preparation'],
            'overrides.*.quantity' => ['sometimes', 'numeric', 'min:0'],
            'overrides.*.unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
            'overrides.*.location_id' => [
                'sometimes',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        $preparation->loadMissing('entities');

        $overrides = collect($validated['overrides'] ?? [])
            ->map(function (array $override) {
                $hasQuantity = array_key_exists('quantity', $override);
                $hasLocation = array_key_exists('location_id', $override);

                if (! $hasQuantity && ! $hasLocation) {
                    throw ValidationException::withMessages([
                        'overrides' => ['Chaque override doit définir une quantité ou une localisation.'],
                    ]);
                }

                if ($hasQuantity) {
                    $override['quantity'] = (float) $override['quantity'];
                }

                if ($hasLocation) {
                    $override['location_id'] = (int) $override['location_id'];
                }

                if (isset($override['unit'])) {
                    $override['unit'] = MeasurementUnit::from($override['unit']);
                }

                return $override;
            })
            ->mapWithKeys(function (array $override) {
                $entityClass = $override['type'] === 'ingredient' ? Ingredient::class : Preparation::class;

                return [$entityClass.'#'.$override['id'] => $override];
            });

        if ($overrides->isNotEmpty()) {
            $componentKeys = $preparation->entities
                ->map(function ($component) {
                    /** @var PreparationEntity $component */
                    return $component->entity_type.'#'.$component->entity_id;
                })
                ->all();

            $invalidOverrides = array_diff(array_keys($overrides->all()), $componentKeys);

            if (! empty($invalidOverrides)) {
                throw ValidationException::withMessages([
                    'overrides' => ['Certaines entités spécifiées ne font pas partie de cette préparation.'],
                ]);
            }
        }

        // Vérifier que l'emplacement de destination appartient à la même entreprise
        $destinationLocation = Location::where('id', $validated['location_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        // Utiliser une transaction pour garantir l'intégrité des données
        try {
            DB::beginTransaction();

            $preparation->load('entities.entity');

            /** @var PreparationEntity $component */
            foreach ($preparation->entities as $component) {
                /** @var Ingredient|Preparation $entity */
                $entity = $component->entity;

                $componentKey = $component->entity_type.'#'.$component->entity_id;
                $override = $overrides->get($componentKey);

                $componentUnit = $component->unit instanceof MeasurementUnit
                    ? $component->unit
                    : MeasurementUnit::from($component->unit);

                $perUnitQuantity = (float) $component->quantity;
                if ($override && array_key_exists('quantity', $override)) {
                    $perUnitQuantity = (float) $override['quantity'];

                    if (! empty($override['unit']) && $override['unit'] instanceof MeasurementUnit) {
                        $perUnitQuantity = $unitConversionService->convert($perUnitQuantity, $override['unit'], $componentUnit);
                    }
                }

                $sourceLocationId = $override['location_id'] ?? $component->location_id;

                $locationEntity = $entity->locations()
                    ->wherePivot('location_id', $sourceLocationId)
                    ->first();

                /** @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot */
                $pivot = $locationEntity->pivot ?? null;

                $required = $perUnitQuantity * $validated['quantity'];

                $entityUnit = $entity->unit instanceof MeasurementUnit
                    ? $entity->unit
                    : MeasurementUnit::from($entity->unit);

                if ($componentUnit !== $entityUnit) {
                    $required = $unitConversionService->convert($required, $componentUnit, $entityUnit);
                }

                if (! $locationEntity || $pivot->quantity < $required) {
                    $stock = $locationEntity ? $pivot->quantity : 0;
                    throw new \Exception("Stock insuffisant pour '{$entity->name}'");
                }

                // Retirer la quantité
                $before = $pivot->quantity;
                $after = $pivot->quantity - $required;
                $entity->locations()->updateExistingPivot($sourceLocationId, ['quantity' => $after]);
                /** @var Location $sourceLocation */
                $sourceLocation = $locationEntity;
                $entity->recordStockMovement($sourceLocation, $before, $after, "Used for Preparation {$preparation->name}");

                if ($entity instanceof Ingredient) {
                    $perishableService->remove($entity->id, $sourceLocationId, $user->company_id, $required);
                }
            }

            // Ajouter la quantité préparée à l'emplacement de destination
            $existingQuantity = 0;
            $locationPrep = $preparation->locations()
                ->wherePivot('location_id', $validated['location_id'])
                ->first();

            if ($locationPrep) {
                /** @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot */
                $pivot = $locationPrep->pivot;
                $existingQuantity = $pivot->quantity;
            }

            $after = $existingQuantity + $validated['quantity'];
            $preparation->locations()->syncWithoutDetaching([
                $validated['location_id'] => ['quantity' => $after],
            ]);
            $preparation->recordStockMovement($destinationLocation, $existingQuantity, $after, "Preparation {$preparation->name} Produced");

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
     * Cas métier : Ajout de stock pour une préparation.
     */
    public function addQuantity(Request $request, $id, StockService $stockService): JsonResponse
    {
        $user = $request->user();

        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        $stockService->add(
            $preparation,
            (int) $validated['location_id'],
            $user->company_id,
            (float) $validated['quantity'],
            null,
            isset($validated['unit']) ? MeasurementUnit::from($validated['unit']) : null
        );

        return response()->json([
            'message' => 'Quantité de la préparation mise à jour avec succès',
            'preparation' => $preparation->load('entities.entity', 'locations', 'category'),
        ], 200);
    }

    /**
     * Cas métier : Retrait de stock pour une préparation.
     */
    public function removeQuantity(Request $request, $id, StockService $stockService): JsonResponse
    {
        $user = $request->user();

        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        try {
            $stockService->remove(
                $preparation,
                (int) $validated['location_id'],
                $user->company_id,
                (float) $validated['quantity'],
                null,
                isset($validated['unit']) ? MeasurementUnit::from($validated['unit']) : null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        return response()->json([
            'message' => 'Quantité de la préparation mise à jour avec succès',
            'preparation' => $preparation->load('entities.entity', 'locations', 'category'),
        ], 200);
    }

    /**
     * Cas métier : Déplacement de stock pour une préparation entre deux emplacements.
     */
    public function moveQuantity(Request $request, $id, StockService $stockService): JsonResponse
    {
        $user = $request->user();

        $preparation = Preparation::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'from_location_id' => ['required', 'integer', 'exists:locations,id'],
            'to_location_id' => ['required', 'integer', 'different:from_location_id', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', Rule::in(MeasurementUnit::values())],
        ]);

        try {
            $stockService->move(
                $preparation,
                (int) $validated['from_location_id'],
                (int) $validated['to_location_id'],
                $user->company_id,
                (float) $validated['quantity'],
                isset($validated['unit']) ? MeasurementUnit::from($validated['unit']) : null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        return response()->json([
            'message' => 'Quantité de la préparation déplacée avec succès',
            'preparation' => $preparation->load('entities.entity', 'locations', 'category'),
        ], 200);
    }
}
