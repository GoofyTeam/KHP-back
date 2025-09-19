<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')
            ->select('id', 'name', 'public_card_url')
            ->orderBy('id')
            ->chunkById(100, function ($companies) {
                foreach ($companies as $company) {
                    if (empty($company->public_card_url)) {
                        DB::table('companies')
                            ->where('id', $company->id)
                            ->update([
                                'public_card_url' => sprintf('%d-%s', $company->id, Str::slug($company->name)),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE companies ALTER COLUMN public_card_url SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE companies ALTER COLUMN public_card_url DROP NOT NULL');
        }
    }
};
