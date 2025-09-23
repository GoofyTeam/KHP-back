<?php

namespace App\Http\Controllers;

use App\Models\QuickAccess;
use Database\Seeders\QuickAccessSeeder;
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
            'quick_accesses' => ['required', 'array', 'min:1'],
            'quick_accesses.*.id' => ['required_with:quick_accesses', 'integer', 'exists:quick_accesses,id'],
            'quick_accesses.*.name' => ['sometimes', 'string', 'max:255'],
            'quick_accesses.*.icon' => ['sometimes', 'string', Rule::in(['Plus', 'Notebook', 'Minus', 'Calendar', 'Check', 'NoIcon', 'User', 'Users', 'ChefHat', 'Cutlery'])],
            'quick_accesses.*.icon_color' => ['sometimes', 'string', Rule::in(['primary', 'warning', 'error', 'info'])],
            'quick_accesses.*.url_key' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($validatedData['quick_accesses'])) {
            foreach ($validatedData['quick_accesses'] as $item) {
                $quickAccess = QuickAccess::where('company_id', $companyId)
                    ->where('id', $item['id'])
                    ->first();

                if ($quickAccess) {
                    $quickAccess->fill(array_filter($item, function ($key) {
                        return in_array($key, ['name', 'icon', 'icon_color', 'url_key']);
                    }, ARRAY_FILTER_USE_KEY));
                    $quickAccess->save();
                }
            }
        }

        $items = QuickAccess::where('company_id', $companyId)->orderBy('index')->get();

        return response()->json([
            'message' => 'Quick accesses updated',
            'quick_accesses' => $items,
        ]);

    }

    /**
     * Réinitialise les 5 boutons de quick access par défaut pour la société de l'utilisateur authentifié.
     */
    public function reset(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $defaults = QuickAccessSeeder::defaults();

        QuickAccess::where('company_id', $companyId)->delete();

        foreach ($defaults as $payload) {
            QuickAccess::create(
                ['company_id' => $companyId] + $payload
            );
        }
        $items = QuickAccess::where('company_id', $companyId)->orderBy('index')->get();

        return response()->json([
            'message' => 'Quick access reset',
            'quick_accesses' => $items,
        ]);
    }
}
