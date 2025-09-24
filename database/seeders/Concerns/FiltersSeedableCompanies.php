<?php

namespace Database\Seeders\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait FiltersSeedableCompanies
{
    /**
     * List of company names that should be ignored by the default seeders.
     *
     * @return list<string>
     */
    protected function excludedCompanyNames(): array
    {
        return [
            'Maison Gustave',
            'La Table des Canuts',
            'Bistro Maelle',
        ];
    }

    /**
     * Retrieve a query builder limited to companies handled by the database seeder.
     */
    protected function seedableCompanyQuery(): Builder
    {
        return Company::query()->whereNotIn('name', $this->excludedCompanyNames());
    }

    /**
     * Retrieve the collection of companies handled by the database seeder.
     *
     * @return Collection<int, Company>
     */
    protected function seedableCompanies(): Collection
    {
        return $this->seedableCompanyQuery()->get();
    }

    /**
     * Retrieve the identifiers of the companies that must be ignored.
     *
     * @return Collection<int, int>
     */
    protected function excludedCompanyIds(): Collection
    {
        static $ids;

        if ($ids === null) {
            $ids = Company::query()
                ->whereIn('name', $this->excludedCompanyNames())
                ->pluck('id');
        }

        return $ids;
    }

    protected function isExcludedCompanyId(?int $companyId): bool
    {
        return $companyId !== null && $this->excludedCompanyIds()->contains($companyId);
    }
}
