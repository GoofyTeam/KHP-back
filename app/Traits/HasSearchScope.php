<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasSearchScope
{
    /**
     * Recherche floue (trigram + ordonnancement par similarité avec seuil ajusté).
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $clean = Str::lower($search);

        // Seuil plus permissif : 0.1 (au lieu de 0.3)
        $threshold = 0.1;

        return $query
            ->whereRaw(
                'similarity(unaccent(lower(name)), unaccent(lower(?))) > ?',
                [$clean, $threshold]
            )
            ->orderByRaw(
                'similarity(unaccent(lower(name)), unaccent(lower(?))) DESC',
                [$clean]
            );
    }
}
