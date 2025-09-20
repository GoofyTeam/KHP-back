<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyOptionsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Cas métier : Mise à jour des options de l'entreprise
     *
     * Use cases :
     * - Modifier la langue utilisée pour les données Open Food Facts
     *
     * Cette fonction permet de modifier certaines options de configuration de
     * l'entreprise connectée, comme la langue utilisée pour récupérer des
     * informations externes.
     *
     * @param  UpdateCompanyOptionsRequest  $request  La requête HTTP contenant les options à modifier
     * @return JsonResponse Confirmation avec les options mises à jour
     */
    public function updateOptions(UpdateCompanyOptionsRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validated();

        if (array_key_exists('open_food_facts_language', $validated)) {
            $company->open_food_facts_language = $validated['open_food_facts_language'];
        }

        if (array_key_exists('public_menu_card_url', $validated)) {
            $company->public_menu_card_url = $validated['public_menu_card_url'];
        }

        if (array_key_exists('show_out_of_stock_menus_on_card', $validated)) {
            $company->show_out_of_stock_menus_on_card = $validated['show_out_of_stock_menus_on_card'];
        }

        if (array_key_exists('show_menu_images', $validated)) {
            $company->show_menu_images = $validated['show_menu_images'];
        }

        $company->save();

        return response()->json([
            'message' => 'Options mises à jour avec succès',
            'data' => [
                'open_food_facts_language' => $company->open_food_facts_language,
                'public_menu_card_url' => $company->public_menu_card_url,
                'show_out_of_stock_menus_on_card' => $company->show_out_of_stock_menus_on_card,
                'show_menu_images' => $company->show_menu_images,
                'only_sufficient_stock' => ! $company->show_out_of_stock_menus_on_card,
                'with_pictures' => $company->show_menu_images,
            ],
        ]);
    }
}
