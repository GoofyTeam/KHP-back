<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menu_categories')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        $category = MenuCategory::create([
            'name' => $validated['name'],
            'company_id' => $user->company_id,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = MenuCategory::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('menu_categories')
                    ->where(fn ($q) => $q->where('company_id', $user->company_id))
                    ->ignore($category->id),
            ],
        ]);

        if (isset($validated['name'])) {
            $category->name = $validated['name'];
            $category->save();
        }

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = MenuCategory::where('company_id', $user->company_id)->findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
