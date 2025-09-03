<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Loss;
use App\Models\Preparation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller gérant l'enregistrement et l'annulation des pertes.
 *
 * Chaque perte déduit immédiatement la quantité de l'emplacement
 * concerné et conserve une trace de l'opération.
 */
class LossController extends Controller
{
    /**
     * Cas métier : Enregistrement d'une perte de stock
     *
     * Use cases :
     * - Un ingrédient est cassé ou renversé
     * - Une préparation est jetée suite à une erreur
     *
     * Cette méthode vérifie la disponibilité de la quantité, la retire
     * de l'emplacement spécifié et enregistre la perte avec sa raison.
     *
     * @param  Request  $request  Données de la perte
     * @return JsonResponse Perte créée avec localisation
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'loss_item_type' => ['required', 'string', 'in:ingredient,preparation'],
            'loss_item_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string'],
        ]);

        $modelClass = $validated['loss_item_type'] === 'ingredient' ? Ingredient::class : Preparation::class;

        $trackable = $modelClass::where('id', $validated['loss_item_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $location = Location::where('id', $validated['location_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        try {
            $loss = $trackable->recordLoss($location, $validated['quantity'], $validated['reason']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'message' => 'Perte enregistrée avec succès',
            'loss' => $loss->load('location'),
        ], 201);
    }

    /**
     * Cas métier : Annulation d'une perte enregistrée
     *
     * Use cases :
     * - La perte a été saisie par erreur
     * - Le produit supposé perdu est retrouvé
     *
     * Cette méthode restaure la quantité retirée et supprime l'entrée
     * de perte correspondante.
     *
     * @param  Request  $request  Requête courante
     * @param  Loss  $loss  Perte à annuler
     * @return JsonResponse Message de confirmation
     */
    public function rollback(Request $request, Loss $loss): JsonResponse
    {
        $user = $request->user();

        if ($loss->company_id !== $user->company_id) {
            return response()->json(['message' => 'Loss not found'], 404);
        }

        try {
            $loss->cancel();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'message' => 'Perte annulée avec succès',
        ]);
    }
}
