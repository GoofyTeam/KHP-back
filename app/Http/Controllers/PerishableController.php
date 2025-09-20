<?php

namespace App\Http\Controllers;

use App\Models\Perishable;
use Illuminate\Http\JsonResponse;

class PerishableController extends Controller
{
    public function markAsRead(Perishable $perishable): JsonResponse
    {
        $user = auth()->user();

        if ($perishable->company_id !== $user->company_id) {
            abort(403);
        }

        if (! $perishable->is_read) {
            $perishable->update(['is_read' => true]);
        }

        return response()->json([
            'perishable' => $perishable->fresh(['ingredient.category.locationTypes', 'location']),
        ]);
    }
}
