<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Loss;
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
            Loss::create([
                'lossable_id' => $perishable->ingredient_id,
                'lossable_type' => Ingredient::class,
                'location_id' => $perishable->location_id,
                'company_id' => $perishable->company_id,
                'user_id' => null,
                'quantity' => $perishable->quantity,
                'reason' => 'expired',
            ]);

            $perishable->delete();
        }

        return 0;
    }
}
