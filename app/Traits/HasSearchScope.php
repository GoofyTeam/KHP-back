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

        /** @phpstan-ignore-next-line property_exists will evaluate to true on models defining the property */
        $column = property_exists($this, 'searchColumn') ? $this->searchColumn : 'name';

        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();

        // Fallback simple LIKE for SQLite, otherwise use trigram similarity
        if ($connection->getDriverName() === 'sqlite') {
            return $query->where($column, 'like', '%'.$clean.'%');
        }

        // Seuil plus permissif : 0.1 (au lieu de 0.3)
        $threshold = 0.1;

        return $query
            ->whereRaw(
                "similarity(unaccent(lower({$column})), unaccent(lower(?))) > ?",
                [$clean, $threshold]
            )
            ->orderByRaw(
                "similarity(unaccent(lower({$column})), unaccent(lower(?))) DESC",
                [$clean]
            );
    }
}
