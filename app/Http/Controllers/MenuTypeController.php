<?php

namespace App\Http\Controllers;

use App\Models\MenuType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuTypeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menu_types')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'public_index' => ['sometimes', 'integer', 'min:0'],
        ]);

        $menuType = MenuType::create([
            'name' => $validated['name'],
            'company_id' => $user->company_id,
        ]);

        $menuType->publicOrder()->create([
            'company_id' => $user->company_id,
            'position' => $validated['public_index'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Menu type created successfully',
            'data' => $menuType->load('publicOrder'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $menuType = MenuType::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('menu_types')
                    ->where(fn ($q) => $q->where('company_id', $user->company_id))
                    ->ignore($menuType->id),
            ],
            'public_index' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (array_key_exists('name', $validated)) {
            $menuType->name = $validated['name'];
        }

        if ($menuType->isDirty()) {
            $menuType->save();
        }

        if (array_key_exists('public_index', $validated)) {
            $menuType->publicOrder()->updateOrCreate(
                ['company_id' => $user->company_id],
                ['position' => $validated['public_index']]
            );
        }

        return response()->json([
            'message' => 'Menu type updated successfully',
            'data' => $menuType->load('publicOrder'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $menuType = MenuType::where('company_id', $user->company_id)->findOrFail($id);

        if ($menuType->menus()->exists()) {
            throw ValidationException::withMessages([
                'menu_type' => ['Impossible de supprimer un type associé à des menus.'],
            ]);
        }

        $menuType->publicOrder()->delete();
        $menuType->delete();

        return response()->json([
            'message' => 'Menu type deleted successfully',
        ]);
    }
}
