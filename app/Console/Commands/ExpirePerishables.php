<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Ingredient;
use App\Models\Perishable;
use Illuminate\Console\Command;
use App\Services\PerishableService;

class ExpirePerishables extends Command
{
    protected $signature = 'perishables:expire';

    protected $description = 'Convert expired perishable stock into losses';

    public function handle(PerishableService $service): int
    {
        $perishables = Perishable::with(['ingredient.category.locationTypes', 'location'])
            ->get()
            ->filter(fn($p) => $service->expiration($p)->isPast());

        foreach ($perishables as $perishable) {
            $ingredient = Ingredient::find($perishable->ingredient_id);
            $location = $perishable->location;

            if ($ingredient && $location instanceof Location) {
                $ingredient->recordLoss($location, $perishable->quantity, 'expired');
            }

            $perishable->delete();
        }

        return 0;
    }
}
