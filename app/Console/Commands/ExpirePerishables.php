<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Perishable;
use App\Services\PerishableService;
use Illuminate\Console\Command;

class ExpirePerishables extends Command
{
    protected $signature = 'perishables:expire';

    protected $description = 'Convert expired perishable stock into losses';

    public function handle(PerishableService $service): int
    {
        $perishables = Perishable::with(['ingredient.category.locationTypes', 'location'])
            ->get()
            ->filter(fn ($p) => $service->expiration($p)->isPast());

        foreach ($perishables as $perishable) {
            $ingredient = Ingredient::find($perishable->ingredient_id);
            if ($ingredient) {
                $ingredient->recordLoss($perishable->location, $perishable->quantity, 'expired');
            }

            $perishable->delete();
        }

        return 0;
    }
}
