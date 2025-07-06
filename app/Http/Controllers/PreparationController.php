<?php

namespace App\Http\Controllers;

use App\Enums\PreparationTypeEnum;
use App\Enums\UnitEnum;
use App\Models\Preparation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PreparationController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                Rule::unique('preparations')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'unit' => [
                'required',
                'enum' => ['required', new Enum(UnitEnum::class)],
            ],
            'type' => [
                'required',
                'enum' => ['required', new Enum(PreparationTypeEnum::class)],
            ],
        ]);

        $preparation = Preparation::create($validated);

        return JsonResponse::create([
            'message' => 'Preparation created successfully',
            'preparation' => $preparation,
        ], 201);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'id' => [
                'required',
                'exists:preparations,id',
                Rule::exists('preparations')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'name' => [
                'optional',
                Rule::unique('preparations')->where(function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                }),
            ],
            'unit' => [
                'optional',
                'enum' => ['required', new Enum(UnitEnum::class)],
            ],
            'type' => [
                'optional',
                'enum' => ['required', new Enum(PreparationTypeEnum::class)],
            ],
        ]);

        $preparation = Preparation::findOrFail($validated['id']);
        $preparation->fill($validated);
        $preparation->save();

        return JsonResponse::create([
            'message' => 'Preparation updated successfully',
            'preparation' => $preparation,
        ], 200);
    }
}
