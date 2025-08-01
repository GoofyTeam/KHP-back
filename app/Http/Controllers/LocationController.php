<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\LocationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    /**
     * Cas métier : Création d'un nouvel emplacement de stockage
     *
     * Use cases :
     * - Ajout d'un nouvel espace de stockage physique
     * - Réorganisation du système de stockage
     * - Création d'un emplacement spécifique pour certains produits
     *
     * Cette fonction permet de créer un nouvel emplacement associé à la compagnie
     * de l'utilisateur connecté et avec un type de localisation spécifié.
     *
     * @param  Request  $request  La requête contenant les données du nouvel emplacement
     * @return JsonResponse La réponse avec l'emplacement créé
     *
     * @throws ValidationException Si les données sont invalides
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:locations,name,NULL,id,company_id,'.Auth::user()->company_id,
            'location_type_id' => 'required|integer|exists:location_types,id',
        ]);

        // Vérifier que le type appartient à la compagnie
        $locationType = LocationType::forCompany()->findOrFail($validated['location_type_id']);

        $location = new Location;
        $location->name = $validated['name'];
        $location->company_id = Auth::user()->company_id;
        $location->location_type_id = $locationType->id;
        $location->save();

        return response()->json([
            'message' => 'Emplacement créé avec succès',
            'data' => $location,
        ], 201);
    }

    /**
     * Cas métier : Modification d'un emplacement existant
     *
     * Use cases :
     * - Correction du nom d'un emplacement
     * - Modification du type d'un emplacement suite à un changement d'usage
     * - Mise à jour des informations après réaménagement
     *
     * Cette fonction permet de modifier le nom et/ou le type d'un emplacement existant.
     *
     * @param  Request  $request  La requête contenant les données à modifier
     * @param  int  $id  L'identifiant de l'emplacement à modifier
     * @return JsonResponse La réponse avec l'emplacement mis à jour
     *
     * @throws ValidationException Si les données sont invalides
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $location = Location::forCompany()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:locations,name,'.$location->id.',id,company_id,'.Auth::user()->company_id,
            'location_type_id' => 'sometimes|required|integer|exists:location_types,id',
        ]);

        if (isset($validated['name'])) {
            $location->name = $validated['name'];
        }

        if (isset($validated['location_type_id'])) {
            // Vérifier que le type appartient à la compagnie
            $locationType = LocationType::forCompany()->findOrFail($validated['location_type_id']);
            $location->location_type_id = $locationType->id;
        }

        $location->save();

        return response()->json([
            'message' => 'Emplacement mis à jour avec succès',
            'data' => $location,
        ]);
    }

    /**
     * Cas métier : Suppression d'un emplacement inutilisé
     *
     * Use cases :
     * - Suppression d'un espace de stockage physique
     * - Nettoyage des emplacements obsolètes
     * - Correction après création accidentelle d'un emplacement en double
     *
     * Cette fonction permet de supprimer un emplacement s'il n'est pas utilisé par des ingrédients.
     *
     * @param  int  $id  L'identifiant de l'emplacement à supprimer
     * @return JsonResponse Message de confirmation ou d'erreur
     */
    public function destroy(int $id): JsonResponse
    {
        $location = Location::forCompany()->findOrFail($id);

        // Vérifier si des ingrédients utilisent cet emplacement
        if ($location->ingredients()->count() > 0) {
            return response()->json([
                'message' => 'Cet emplacement contient des ingrédients et ne peut pas être supprimé.',
            ], 409);
        }

        $location->delete();

        return response()->json([
            'message' => 'Emplacement supprimé avec succès',
        ]);
    }

    /**
     * Cas métier : Association d'un emplacement à un type de localisation
     *
     * Use cases :
     * - Reclassification d'un emplacement existant
     * - Correction d'une erreur de classification initiale
     * - Organisation des emplacements selon une nouvelle typologie
     * - Standardisation des emplacements dans l'entreprise
     *
     * Cette fonction permet d'associer un emplacement existant à un type de localisation.
     * Les deux entités doivent appartenir à la compagnie de l'utilisateur connecté.
     *
     * @param  Request  $request  La requête contenant les identifiants
     * @return JsonResponse La réponse avec le résultat de l'opération
     *
     * @throws ValidationException Si les données sont invalides
     */
    public function assignType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'location_type_id' => 'required|integer|exists:location_types,id',
        ]);

        // Récupérer l'emplacement en vérifiant qu'il appartient à la compagnie
        $location = Location::forCompany()->findOrFail($validated['location_id']);

        // Récupérer le type en vérifiant qu'il appartient à la compagnie
        $locationType = LocationType::forCompany()->findOrFail($validated['location_type_id']);

        // Association de l'emplacement au type
        $location->location_type_id = $locationType->id;
        $location->save();

        return response()->json([
            'message' => "L'emplacement '{$location->name}' a été associé au type '{$locationType->name}'",
            'data' => $location,
        ]);
    }
}
