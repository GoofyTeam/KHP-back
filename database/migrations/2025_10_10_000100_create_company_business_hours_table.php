<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_business_hours')) {
            Schema::create('company_business_hours', function (Blueprint $table) {
                $table->id()->comment('Identifiant technique unique d\'un créneau horaire.');
                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete()
                    ->comment('Lien vers la compagnie propriétaire de ce créneau; supprimé automatiquement si la compagnie disparaît.');
                $table->unsignedTinyInteger('day_of_week')
                    ->comment('Jour ISO-8601 (1=lundi ... 7=dimanche) auquel appartient le créneau dans l\'agenda du restaurant.');
                $table->time('opens_at')
                    ->comment('Heure locale d\'ouverture du service (format HH:MM) pour ce créneau spécifique.');
                $table->time('closes_at')
                    ->comment('Heure locale de fermeture du service. Si elle est inférieure à opens_at, le flag is_overnight doit être activé.');
                $table->boolean('is_overnight')
                    ->default(false)
                    ->comment('Indique qu\'un service traverse minuit et se termine le jour suivant, conservant ainsi la continuité métier.');
                $table->unsignedTinyInteger('sequence')
                    ->default(0)
                    ->comment('Ordre d\'affichage normalisé des créneaux d\'une même journée (0 pour le premier, 1 pour le second, etc.).');
                $table->timestamp('created_at')->nullable()->comment('Date d\'enregistrement du créneau pour le suivi et l\'audit.');
                $table->timestamp('updated_at')->nullable()->comment('Date de dernière modification du créneau pour refléter les ajustements d\'horaires.');

                $table->unique(['company_id', 'day_of_week', 'opens_at', 'closes_at'], 'company_hours_unique_interval');
                $table->index(['company_id', 'day_of_week', 'sequence'], 'company_hours_day_sequence_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_business_hours');
    }
};
