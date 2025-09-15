<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    /**
     * Crée une ou plusieurs tables dans une salle.
     */
    public function store(Request $request, int $roomId): JsonResponse
    {
        $room = Room::forCompany()->findOrFail($roomId);

        $validated = $request->validate([
            'label' => [
                'sometimes', 'string',
                Rule::unique('tables')->where(fn ($q) => $q->where('room_id', $room->id)),
            ],
            'seats' => 'required|integer|min:1',
            'count' => 'sometimes|integer|min:1',
        ]);

        if (($validated['count'] ?? 1) > 1 && isset($validated['label'])) {
            return response()->json([
                'message' => 'Label non autorisé lors de la création multiple',
            ], 422);
        }

        $count = $validated['count'] ?? 1;
        $tables = [];

        $existingTables = $room->tables()
            ->where('label', 'like', $room->code.'%')
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Table> $existingTables */
        $nextIndex = (int) $existingTables
            ->map(fn (Table $t): int => (int) substr($t->label, strlen($room->code)))
            ->filter()
            ->max() + 1;
        if ($nextIndex < 1) {
            $nextIndex = 1;
        }

        for ($i = 0; $i < $count; $i++) {
            $label = $validated['label'] ?? ($room->code.($nextIndex + $i));
            $tables[] = Table::create([
                'label' => $label,
                'seats' => $validated['seats'],
                'room_id' => $room->id,
                'company_id' => Auth::user()->company_id,
            ]);
        }

        return response()->json([
            'message' => 'Tables créées avec succès',
            'data' => $tables,
        ], 201);
    }

    /**
     * Met à jour une table.
     */
    public function update(Request $request, int $roomId, int $tableId): JsonResponse
    {
        $room = Room::forCompany()->findOrFail($roomId);
        $table = Table::where('room_id', $room->id)->where('company_id', $room->company_id)->findOrFail($tableId);

        $validated = $request->validate([
            'label' => [
                'sometimes', 'string',
                Rule::unique('tables')->where(fn ($q) => $q->where('room_id', $room->id))->ignore($table->id),
            ],
            'seats' => 'sometimes|integer|min:1',
        ]);

        if (isset($validated['label'])) {
            $table->label = $validated['label'];
        }
        if (isset($validated['seats'])) {
            $table->seats = $validated['seats'];
        }
        $table->save();

        return response()->json([
            'message' => 'Table mise à jour avec succès',
            'data' => $table,
        ]);
    }

    /**
     * Supprime une table.
     */
    public function destroy(int $roomId, int $tableId): JsonResponse
    {
        $room = Room::forCompany()->findOrFail($roomId);
        $table = Table::where('room_id', $room->id)->where('company_id', $room->company_id)->findOrFail($tableId);
        $table->delete();

        return response()->json([
            'message' => 'Table supprimée avec succès',
        ]);
    }
}
