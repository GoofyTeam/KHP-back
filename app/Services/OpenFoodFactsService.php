<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OpenFoodFactsService
{
    protected string $baseUrl;

    protected string $userAgent;

    public function __construct()
    {
        $this->baseUrl = config('openfoodfacts.base_url');
    }

    /**
     * Initialise le client HTTP configuré pour l'API.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Récupère un produit par code-barres.
     *
     * @return array|null Données JSON du produit ou null si non trouvé
     */
    public function searchByBarcode(string $barcode): ?array
    {
        $response = $this->client()
            ->get("/api/v2/product/{$barcode}.json");

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Recherche des produits par mots-clés.
     *
     * @return array Résultat brut de l'API
     */
    public function searchByKeyword(string $query, int $page = 1, int $pageSize = 20): array
    {
        $response = $this->client()
            ->get('/api/v2/search', [
                'search_terms' => $query,
                'page' => $page,
                'page_size' => $pageSize,
            ]);

        return $response->successful() ? $response->json() : [];
    }
}
