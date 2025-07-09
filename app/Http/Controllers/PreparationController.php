<?php

namespace App\Http\Controllers;

use App\Enums\PreparationTypeEnum;
use App\Models\Preparation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PreparationController extends Controller
{
    /**
     * Create a new preparation
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('preparations')->where('company_id', $user->company_id),
            ],
            'unit' => [
                'required',
                'string',
                'max:255',
            ],
            'type' => [
                'required',
                new Enum(PreparationTypeEnum::class),
            ],
        ], [
            'type' => 'Le champ type doit être l\'une des valeurs suivantes : '.implode(', ', PreparationTypeEnum::values()),
        ]);

        $validated['company_id'] = $user->company_id;

        $preparation = Preparation::create($validated);

        return response()->json([
            'message' => 'Preparation created successfully',
            'preparation' => $preparation,
        ], 201);
    }

    /**
     * Update an existing preparation
     *
     * @param  int  $id
     */
    public function update(Request $request, $id): JsonResponse
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
                Rule::unique('preparations')->where('company_id', $user->company_id)->ignore($id),
            ],
            'unit' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'type' => [
                'sometimes',
                new Enum(PreparationTypeEnum::class),
            ],
        ], [
            'type' => 'Le champ type doit être l\'une des valeurs suivantes : '.implode(', ', PreparationTypeEnum::values()),
        ]);

        $preparation->update($validated);

        return response()->json([
            'message' => 'Preparation updated successfully',
            'preparation' => $preparation,
        ], 200);
    }
}
