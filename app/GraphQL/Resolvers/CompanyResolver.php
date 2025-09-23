<?php

namespace App\GraphQL\Resolvers;

use App\Models\Company;

class CompanyResolver
{
    public function logoPath(Company $company): ?string
    {
        if (! $company->logo_path) {
            return null;
        }

        return url('/api/image-proxy/'.$company->logo_path);
    }
}
