<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Loss;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LossController extends Controller
{
    /**
     * Enregistrer une perte
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'entity_type' => 'required|string', // ex: App\Models\Ingredient
            'entity_id' => 'required|integer',
            'location_id' => 'required|integer|exists:locations,id',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            // Récupérer l'entité réelle
            $entityClass = $data['entity_type'];
            $entity = $entityClass::findOrFail($data['entity_id']);

            // Diminuer le stock dans la localisation
            $location = Location::findOrFail($data['location_id']);
            $currentQty = $entity->locations()->where('location_id', $location->id)->first()->pivot->quantity ?? 0;
            $entity->locations()->updateExistingPivot($location->id, [
                'quantity' => max(0, $currentQty - $data['quantity']),
            ]);

            // Enregistrer la perte
            Loss::create($data);
        });

        return response()->json(['message' => 'Perte enregistrée avec succès'], 201);
    }

    /**
     * Supprimer une perte et restaurer le stock
     */
    public function destroy(Loss $loss)
    {
        DB::transaction(function () use ($loss) {
            // Récupérer l'entité réelle
            $entityClass = $loss->entity_type;
            $entity = $entityClass::findOrFail($loss->entity_id);

            // Restaurer le stock dans la localisation
            $location = Location::findOrFail($loss->location_id);
            $currentQty = $entity->locations()->where('location_id', $location->id)->first()->pivot->quantity ?? 0;
            $entity->locations()->updateExistingPivot($location->id, [
                'quantity' => $currentQty + $loss->quantity,
            ]);

            // Supprimer la perte
            $loss->delete();
        });

        return response()->json(['message' => 'Perte supprimée et stock restauré']);
    }
}
