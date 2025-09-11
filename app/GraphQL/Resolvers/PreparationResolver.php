<?php

namespace App\GraphQL\Resolvers;

use App\Models\Preparation;

class PreparationResolver
{
    public function imageUrl(Preparation $preparation): ?string
    {
        if (! $preparation->image_url) {
            return null;
        }

        return url('/api/image-proxy/'.$preparation->image_url);
    }

    /**
     * Récupère les quantités par emplacement pour une préparation
     *
     * @param  Preparation  $preparation  La préparation concernée
     * @return array Tableau des quantités avec leurs emplacements associés
     */
    public function quantityByLocation(Preparation $preparation): array
    {
        $quantities = [];

        /** @var \App\Models\Location $location */
        foreach ($preparation->locations as $location) {
            /**
             * @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot
             */
            $pivot = $location->pivot;
            $quantities[] = [
                'quantity' => $pivot->quantity,
                'location' => $location,
            ];
        }

        return $quantities;
    }
}
