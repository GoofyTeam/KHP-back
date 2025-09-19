<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')
            ->select('id', 'name')
            ->whereNull('public_card_url')
            ->orderBy('id')
            ->chunkById(100, function ($companies) {
                foreach ($companies as $company) {
                    DB::table('companies')
                        ->where('id', $company->id)
                        ->update([
                            'public_card_url' => sprintf('%d-%s', $company->id, Str::slug($company->name)),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // No rollback: removing generated slugs could break public URLs.
    }
};
