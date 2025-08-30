<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Perishable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PerishableService
{
    public function add(int $ingredientId, int $locationId, int $companyId, float $quantity): ?Perishable
    {
        $ingredient = Ingredient::with('category.locationTypes')->find($ingredientId);
        $location = Location::find($locationId);

        if (! $ingredient || ! $location) {
            return null;
        }

        /** @var Category|null $category */
        $category = $ingredient->category;

        $shelfLife = $category?->locationTypes()
            ->where('location_type_id', $location->location_type_id)
            ->first()
            ?->pivot?->getAttribute('shelf_life_hours');

        if (! $shelfLife) {
            return null;
        }

        return Perishable::create([
            'ingredient_id' => $ingredientId,
            'location_id' => $locationId,
            'company_id' => $companyId,
            'quantity' => $quantity,
        ]);
    }

    public function remove(int $ingredientId, int $locationId, int $companyId, float $quantity): void
    {
        $perishables = Perishable::with(['ingredient.category.locationTypes', 'location'])
            ->where('ingredient_id', $ingredientId)
            ->where('location_id', $locationId)
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn ($p) => $this->expiration($p)->isFuture())
            ->sortBy(fn ($p) => $this->expiration($p));

        foreach ($perishables as $perishable) {
            if ($quantity <= 0) {
                break;
            }

            $remove = min($perishable->quantity, $quantity);
            $perishable->quantity -= $remove;
            $quantity -= $remove;

            if ($perishable->quantity <= 0) {
                $perishable->delete();
            } else {
                $perishable->save();
            }
        }
    }

    public function expiration(Perishable $perishable): CarbonInterface
    {
        /** @var Location $location */
        $location = $perishable->location;

        /** @var Ingredient $ingredient */
        $ingredient = $perishable->ingredient;

        /** @var Category|null $category */
        $category = $ingredient->category;

        $locationTypeId = $location->location_type_id;
        $shelfLife = $category?->locationTypes()
            ->where('location_type_id', $locationTypeId)
            ->first()
            ?->pivot?->getAttribute('shelf_life_hours');

        if (! $shelfLife) {
            return Carbon::create(9999, 12, 31, 23, 59, 59);
        }

        return $perishable->created_at->copy()->addHours($shelfLife);
    }
}
