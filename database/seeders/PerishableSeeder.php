<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Loss;
use App\Services\PerishableService;
use Database\Seeders\Concerns\FiltersSeedableCompanies;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerishableSeeder extends Seeder
{
    use FiltersSeedableCompanies;

    public function run(PerishableService $service): void
    {
        $rows = DB::table('ingredient_location')->where('quantity', '>', 0)->get();

        $statuses = ['FRESH', 'SOON', 'EXPIRED'];
        $index = 0;

        foreach ($rows as $row) {
            $ingredient = Ingredient::find($row->ingredient_id);
            if (! $ingredient) {
                continue;
            }
            $companyId = $ingredient->company_id;

            if ($this->isExcludedCompanyId($companyId)) {
                continue;
            }

            $perishable = $service->add($row->ingredient_id, $row->location_id, $companyId, $row->quantity);
            if (! $perishable) {
                continue; // not perishable
            }

            $shelfLife = $service->expiration($perishable)->diffInHours($perishable->created_at);
            $status = $statuses[$index % count($statuses)];
            $index++;

            if ($status === 'SOON') {
                $perishable->created_at = now()->subHours(max($shelfLife - 24, 1));
                $perishable->save();
            } elseif ($status === 'EXPIRED') {
                $perishable->created_at = now()->subHours($shelfLife + 1);
                $perishable->save();

                Loss::create([
                    'loss_item_id' => $perishable->ingredient_id,
                    'loss_item_type' => Ingredient::class,
                    'location_id' => $perishable->location_id,
                    'company_id' => $perishable->company_id,
                    'user_id' => null,
                    'quantity' => $perishable->quantity,
                    'reason' => 'expired',
                ]);

                $perishable->delete();
            }
        }
    }
}
