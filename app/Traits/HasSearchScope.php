<?php

namespace App\Traits;

trait HasSearchScope
{
    /**
     * Search by name, with support for partial matches and fuzzy matching.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            // Nettoyage du terme de recherche
            $search = trim($search);

            return $query->where(function ($q) use ($search) {
                // Recherche par contenu (sous-chaîne)
                $q->whereRaw('unaccent(name) ILIKE unaccent(?)', ["%{$search}%"])
                    // Recherche par similarité globale avec un seuil abaissé
                    ->orWhereRaw('similarity(unaccent(name), unaccent(?)) > 0.2', [$search])
                    // Recherche de mots individuels avec plusieurs approches
                    ->orWhereRaw("EXISTS (
                        SELECT 1
                        FROM regexp_split_to_table(unaccent(name), E'\\\\s+|\\\\-') word
                        WHERE similarity(word, unaccent(?)) > 0.4
                           OR levenshtein(lower(unaccent(word)), lower(unaccent(?))) <= 2
                           OR word ILIKE ?
                    )", [$search, $search, "%{$search}%"]);
            });
        }

        return $query;
    }
}
