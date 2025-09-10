<?php

namespace App\Http\Controllers;

use App\Models\QuickAccess;
use App\Models\SpecialQuickAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickAccessController extends Controller
{
    /**
     * Met à jour un ou plusieurs quick access en une seule requête pour la société de l'utilisateur authentifié.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $validatedData = $request->validate([
            'quick_accesses' => ['required_without:special_quick_access', 'array', 'min:1'],
            'quick_accesses.*.id' => ['required_with:quick_accesses', 'integer', 'exists:quick_accesses,id'],
            'quick_accesses.*.name' => ['sometimes', 'string', 'max:255'],
            'quick_accesses.*.icon' => ['sometimes', 'string', 'max:255'],
            'quick_accesses.*.icon_color' => ['sometimes', 'string', Rule::in(['primary', 'warning', 'error', 'info'])],
            'quick_accesses.*.url' => ['sometimes', 'string', 'max:255'],

            'special_quick_access' => ['required_without:quick_accesses', 'array'],
            'special_quick_access.name' => ['sometimes', 'string', 'max:255'],
            'special_quick_access.url' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($validatedData['quick_accesses'])) {
            foreach ($validatedData['quick_accesses'] as $item) {
                $quickAccess = QuickAccess::where('company_id', $companyId)
                    ->where('id', $item['id'])
                    ->first();

                if ($quickAccess) {
                    $quickAccess->fill(array_filter($item, function ($key) {
                        return in_array($key, ['name', 'icon', 'icon_color', 'url']);
                    }, ARRAY_FILTER_USE_KEY));
                    $quickAccess->save();
                }
            }
        }

        $special = null;
        if (isset($validatedData['special_quick_access'])) {
            $payload = array_filter($validatedData['special_quick_access'], function ($key) {
                return in_array($key, ['name', 'url']);
            }, ARRAY_FILTER_USE_KEY);

            if (! empty($payload)) {
                $special = SpecialQuickAccess::updateOrCreate(
                    ['company_id' => $companyId],
                    $payload
                );
            } else {
                $special = SpecialQuickAccess::where('company_id', $companyId)->first();
            }
        } else {
            $special = SpecialQuickAccess::where('company_id', $companyId)->first();
        }

        $items = QuickAccess::where('company_id', $companyId)->orderBy('index')->get();

        return response()->json([
            'message' => 'Quick accesses updated',
            'quick_accesses' => $items,
            'special_quick_access' => $special,
        ]);

    }

    /**
     * Réinitialise les 5 boutons de quick access par défaut pour la société de l'utilisateur authentifié.
     */
    public function reset(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $defaults = [
            1 => ['name' => 'Add to stock', 'icon' => 'Plus', 'icon_color' => 'primary', 'url' => '/stock/add'],
            2 => ['name' => 'Menu Card', 'icon' => 'Notebook', 'icon_color' => 'info', 'url' => '/menucard'],
            3 => ['name' => 'Stock', 'icon' => 'Check', 'icon_color' => 'primary', 'url' => '/stock'],
            4 => ['name' => 'Take Order', 'icon' => 'Notebook', 'icon_color' => 'primary', 'url' => '/takeorder'],
        ];

        foreach ($defaults as $pos => $payload) {
            QuickAccess::updateOrCreate(
                ['company_id' => $companyId, 'index' => $pos],
                $payload
            );
        }

        SpecialQuickAccess::updateOrCreate(
            ['company_id' => $companyId],
            ['name' => 'Move Quantity', 'url' => '/movequantity']
        );

        $items = QuickAccess::where('company_id', $companyId)->orderBy('index')->get();
        $special = SpecialQuickAccess::where('company_id', $companyId)->first();

        return response()->json([
            'message' => 'Quick access reset',
            'quick_accesses' => $items,
            'special_quick_access' => $special,
        ]);
    }
}
