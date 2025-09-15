<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    /**
     * Crée une nouvelle salle.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('rooms')->where(fn ($q) => $q->where('company_id', Auth::user()->company_id)),
            ],
        ]);

        $room = Room::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'company_id' => Auth::user()->company_id,
        ]);

        return response()->json([
            'message' => 'Salle créée avec succès',
            'data' => $room,
        ], 201);
    }

    /**
     * Met à jour une salle existante.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $room = Room::forCompany()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes', 'required', 'string', 'max:10',
                Rule::unique('rooms')->where(fn ($q) => $q->where('company_id', Auth::user()->company_id))->ignore($room->id),
            ],
        ]);

        if (isset($validated['name'])) {
            $room->name = $validated['name'];
        }
        if (isset($validated['code'])) {
            $room->code = $validated['code'];
        }
        $room->save();

        return response()->json([
            'message' => 'Salle mise à jour avec succès',
            'data' => $room,
        ]);
    }

    /**
     * Supprime une salle.
     */
    public function destroy(int $id): JsonResponse
    {
        $room = Room::forCompany()->findOrFail($id);
        $room->delete();

        return response()->json([
            'message' => 'Salle supprimée avec succès',
        ]);
    }
}
