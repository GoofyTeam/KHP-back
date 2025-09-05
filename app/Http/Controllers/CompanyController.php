<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Cas métier : Mise à jour des options de l'entreprise
     *
     * Use cases :
     * - Activer ou désactiver la complétion automatique des commandes de menu
     *
     * Cette fonction permet de modifier certaines options de configuration de
     * l'entreprise connectée, comme la complétion automatique des commandes de
     * menu.
     *
     * @param  Request  $request  La requête HTTP contenant les options à modifier
     * @return JsonResponse Confirmation avec les options mises à jour
     */
    public function updateOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validate([
            'auto_complete_menu_orders' => 'sometimes|boolean',
            'open_food_facts_language' => 'sometimes|in:fr,en',
        ]);

        if (array_key_exists('auto_complete_menu_orders', $validated)) {
            $company->auto_complete_menu_orders = $validated['auto_complete_menu_orders'];
        }

        if (array_key_exists('open_food_facts_language', $validated)) {
            $company->open_food_facts_language = $validated['open_food_facts_language'];
        }

        $company->save();

        return response()->json([
            'message' => 'Options mises à jour avec succès',
            'data' => [
                'auto_complete_menu_orders' => $company->auto_complete_menu_orders,
                'open_food_facts_language' => $company->open_food_facts_language,
            ],
        ]);
    }
}
