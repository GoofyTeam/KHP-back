<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePublicMenuSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class PublicMenuSettingsController extends Controller
{
    public function __invoke(UpdatePublicMenuSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validated();

        if (array_key_exists('public_card_url', $validated)) {
            $company->public_card_url = $validated['public_card_url'];
        }

        if (array_key_exists('only_sufficient_stock', $validated)) {
            $company->show_out_of_stock_menus = ! (bool) $validated['only_sufficient_stock'];
        }

        if (array_key_exists('with_pictures', $validated)) {
            $company->show_menu_images = (bool) $validated['with_pictures'];
        }

        $company->save();

        return response()->json([
            'message' => 'Paramètres publics mis à jour avec succès',
            'data' => [
                'public_card_url' => $company->public_card_url,
                'only_sufficient_stock' => ! $company->show_out_of_stock_menus,
                'with_pictures' => $company->show_menu_images,
            ],
        ]);
    }
}
