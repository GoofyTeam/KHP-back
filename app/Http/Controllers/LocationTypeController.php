<?php

namespace App\Http\Controllers;

use App\Models\LocationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LocationTypeController extends Controller
{
    /**
     * Cas métier : Création d'un nouveau type de localisation
     *
     * Use cases :
     * - Un chef de cuisine souhaite ajouter un nouveau type pour mieux organiser ses stocks
     * - Un gestionnaire souhaite créer un type spécifique pour un nouveau local de stockage
     * - Un administrateur veut étendre la typologie de stockage existante
     *
     * Cette fonction permet de créer un nouveau type de localisation pour la compagnie
     * de l'utilisateur connecté. Le nom doit être unique au sein de la compagnie.
     *
     * @param  Request  $request  La requête contenant le nom du nouveau type
     * @return JsonResponse La réponse avec le nouveau type créé
     *
     * @throws ValidationException Si le nom est invalide ou déjà utilisé
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:location_types,name,NULL,id,company_id,'.Auth::user()->company_id,
        ]);

        $locationType = new LocationType;
        $locationType->name = $validated['name'];
        $locationType->company_id = Auth::user()->company_id;
        $locationType->is_default = false;
        $locationType->save();

        return response()->json([
            'message' => 'Type de localisation créé avec succès',
            'data' => $locationType,
        ], 201);
    }

    /**
     * Cas métier : Modification d'un type de localisation existant
     *
     * Use cases :
     * - Correction d'une faute d'orthographe dans le nom d'un type
     * - Précision ou clarification du nom d'un type existant
     * - Renommer un type pour mieux refléter son usage actuel
     *
     * Cette fonction permet de mettre à jour le nom d'un type de localisation.
     * Les types par défaut (Congélateur, Réfrigérateur, Autre) ne peuvent pas être renommés.
     *
     * @param  Request  $request  La requête contenant le nouveau nom
     * @param  int  $id  L'identifiant du type de localisation à modifier
     * @return JsonResponse La réponse avec le type mis à jour
     *
     * @throws ValidationException Si le nom est invalide ou si on tente de renommer un type par défaut
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $locationType = LocationType::forCompany()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:location_types,name,'.$locationType->id.',id,company_id,'.Auth::user()->company_id,
        ]);

        // Empêcher la modification du nom pour les types par défaut
        if ($locationType->is_default && $locationType->name !== $validated['name']) {
            throw ValidationException::withMessages([
                'name' => ['Les types de localisation par défaut ne peuvent pas être renommés.'],
            ]);
        }

        $locationType->name = $validated['name'];
        $locationType->save();

        return response()->json([
            'message' => 'Type de localisation mis à jour avec succès',
            'data' => $locationType,
        ]);
    }

    /**
     * Cas métier : Suppression d'un type de localisation inutilisé
     *
     * Use cases :
     * - Nettoyage des types obsolètes ou inutilisés
     * - Correction après création accidentelle d'un type en double
     * - Simplification de la typologie pour une meilleure organisation
     *
     * Cette fonction permet de supprimer un type de localisation sous certaines conditions :
     * - Le type ne doit pas être un type par défaut (Congélateur, Réfrigérateur, Autre)
     * - Le type ne doit pas être utilisé par des emplacements existants
     *
     * @param  int  $id  L'identifiant du type de localisation à supprimer
     * @return JsonResponse Message de confirmation ou d'erreur
     */
    public function destroy(int $id): JsonResponse
    {
        $locationType = LocationType::forCompany()->findOrFail($id);

        // Vérifier si c'est un type par défaut
        if ($locationType->is_default) {
            return response()->json([
                'message' => 'Les types de localisation par défaut ne peuvent pas être supprimés.',
            ], 403);
        }

        // Vérifier si des locations utilisent ce type
        if ($locationType->locations()->count() > 0) {
            return response()->json([
                'message' => 'Ce type de localisation est utilisé par des emplacements et ne peut pas être supprimé.',
            ], 409);
        }

        $locationType->delete();

        return response()->json([
            'message' => 'Type de localisation supprimé avec succès',
        ]);
    }
}
