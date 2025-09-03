<?php

namespace App\Http\Controllers;

use App\Models\LossReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LossReasonController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('loss_reasons')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        $reason = LossReason::create([
            'name' => $validated['name'],
            'company_id' => $user->company_id,
        ]);

        return response()->json([
            'message' => 'Raison créée avec succès',
            'data' => $reason,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reason = LossReason::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('loss_reasons')->where(fn ($q) => $q->where('company_id', $user->company_id))->ignore($reason->id),
            ],
        ]);

        $reason->update(['name' => $validated['name']]);

        return response()->json([
            'message' => 'Raison mise à jour avec succès',
            'data' => $reason,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reason = LossReason::where('company_id', $user->company_id)->findOrFail($id);
        $reason->delete();

        return response()->json([
            'message' => 'Raison supprimée avec succès',
        ]);
    }
}
