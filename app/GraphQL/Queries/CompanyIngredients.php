<?php

namespace App\GraphQL\Queries;

use App\Models\Company;

class CompanyIngredients
{
    /**
     * Récupérer tous les ingrédients associés à une entreprise.
     *
     * @return \Illuminate\Support\Collection
     */
    public function resolve(Company $company, array $args)
    {
        return $company->ingredients();
    }
}
