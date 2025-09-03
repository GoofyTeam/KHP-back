<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loss_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        $this->createDefaultReasons();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loss_reasons');
    }

    /**
     * Create default reasons for existing companies.
     */
    private function createDefaultReasons(): void
    {
        $reasons = [
            'Expired',
            'Broken',
            'Spilled',
            'Contaminated',
            'Damaged',
            'Lost',
            'Other',
        ];

        foreach (Company::all() as $company) {
            foreach ($reasons as $reason) {
                DB::table('loss_reasons')->insert([
                    'name' => $reason,
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
