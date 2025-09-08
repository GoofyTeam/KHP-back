<?php

namespace App\Http\Controllers;

use App\Models\QuickAccess;
use App\Models\SpecialQuickAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickAccessController extends Controller
{
    /**
     * Lister les 5 boutons quick access pour l'utilisateur authentifié.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $items = QuickAccess::where('user_id', $user->id)
            ->orderBy('index')
            ->get();

        $special = SpecialQuickAccess::where('user_id', $user->id)->first();

        return response()->json([
            'quick_accesses' => $items,
            'special_quick_access' => $special,
        ]);
    }

    /**
    * Met à jour une quick access par sa position (1..5) pour l'utilisateur authentifié.
     */
    public function update(Request $request, int $position)
    {
        $user = $request->user();

        if ($position < 1 || $position > 5) {
            return response()->json(['message' => 'Position must be between 1 and 5'], 422);
        }

        if ($position === 5) {
            // Special quick access: seulement le nom et l'url existent
            $data = $request->validate([
                'name' => ['sometimes', 'string', 'max:26'],
                'url' => ['sometimes', 'string'],
            ]);

            $item = SpecialQuickAccess::where('user_id', $user->id)->first();
            if (! $item) {
                $item = SpecialQuickAccess::factory()->create([
                    'user_id' => $user->id,
                    'name' => $data['name'] ?? 'Move Quantity',
                    'url' => $data['url'] ?? '/movequantity',
                ]);
            } else {
                $item->update($data);
            }

            return response()->json(['message' => 'Special quick access updated', 'special_quick_access' => $item->fresh()]);
        }

        // Positions 1..4
        $rules = [
            'name' => ['sometimes', 'string', 'max:26'],
            'url' => ['sometimes', 'string'],
            'icon' => ['sometimes', Rule::in(['Plus', 'Notebook', 'Minus', 'Calendar', 'Check'])],
            'icon_color' => ['sometimes', 'integer', Rule::in([1, 2, 3, 4])],
        ];
        $data = $request->validate($rules);

        $item = QuickAccess::where('user_id', $user->id)
            ->where('index', $position)
            ->first();

        if (! $item) {
            // Exiger l'icône et la couleur lors de la création d'une nouvelle entrée pour éviter les valeurs nulles
            $request->validate([
                'icon' => ['required', Rule::in(['Plus', 'Notebook', 'Minus', 'Calendar', 'Check'])],
                'icon_color' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            ]);

            $item = QuickAccess::create([
                'user_id' => $user->id,
                'index' => $position,
                'name' => $data['name'] ?? 'Button '.$position,
                'url' => $data['url'] ?? '/',
                'icon' => $data['icon'],
                'icon_color' => $data['icon_color'],
            ]);
        } else {
            $item->update($data);
        }

        return response()->json(['message' => 'Quick access updated', 'quick_access' => $item->fresh()]);
    }

    /**
    * Réinitialise les 5 boutons de quick access par défaut pour l'utilisateur authentifié.
     */
    public function reset(Request $request)
    {
        $user = $request->user();

        $defaults = [
            1 => ['name' => 'Add to stock', 'icon' => 'Plus', 'icon_color' => 1, 'url' => '/stock/add'],
            2 => ['name' => 'Menu Card', 'icon' => 'Notebook', 'icon_color' => 4, 'url' => '/menucard'],
            3 => ['name' => 'Stock', 'icon' => 'Check', 'icon_color' => 1, 'url' => '/stock'],
            4 => ['name' => 'Take Order', 'icon' => 'Notebook', 'icon_color' => 1, 'url' => '/takeorder'],
        ];

        foreach ($defaults as $pos => $payload) {
            QuickAccess::updateOrCreate(
                ['user_id' => $user->id, 'index' => $pos],
                $payload
            );
        }

        SpecialQuickAccess::updateOrCreate(
            ['user_id' => $user->id],
            ['name' => 'Move Quantity', 'url' => '/movequantity']
        );

        $items = QuickAccess::where('user_id', $user->id)->orderBy('index')->get();
        $special = SpecialQuickAccess::where('user_id', $user->id)->first();

        return response()->json([
            'message' => 'Quick access reset',
            'quick_accesses' => $items,
            'special_quick_access' => $special,
        ]);
    }
}
