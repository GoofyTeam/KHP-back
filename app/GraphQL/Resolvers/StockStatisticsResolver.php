<?php

namespace App\GraphQL\Resolvers;

use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockStatisticsResolver
{
    /**
     * Obtenir les statistiques des mouvements de stock pour une période donnée
     */
    public function getStatistics($root, array $args)
    {
        // Récupérer les arguments
        $startDate = Carbon::parse($args['startDate']);
        $endDate = Carbon::parse($args['endDate']);
        $locationId = $args['locationId'] ?? null;
        $type = $args['type'] ?? null;

        // Base de la requête - uniquement pour l'entreprise de l'utilisateur connecté
        $query = StockMovement::forCompany()
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Ajouter le filtre par emplacement si spécifié
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Ajouter le filtre par type si spécifié
        if ($type) {
            $query->where('type', $type);
        }

        // Nombre total de mouvements
        $totalMovements = $query->count();

        // Quantités totales par type
        $totals = $query->selectRaw('
            SUM(CASE WHEN type = "addition" THEN quantity ELSE 0 END) as totalAdditions,
            SUM(CASE WHEN type = "withdrawal" THEN quantity ELSE 0 END) as totalWithdrawals
        ')->first();

        // Mouvements par jour
        $movementsByDay = $query->selectRaw('
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(CASE WHEN type = "addition" THEN quantity ELSE 0 END) as additions,
            SUM(CASE WHEN type = "withdrawal" THEN quantity ELSE 0 END) as withdrawals
        ')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'additions' => (float) $item->additions,
                    'withdrawals' => (float) $item->withdrawals,
                ];
            });

        // Top 5 des ingrédients les plus utilisés
        $topIngredients = StockMovement::forCompany()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('trackable_type', 'App\\Models\\Ingredient')
            ->when($locationId, function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            })
            ->when($type, function ($q) use ($type) {
                $q->where('type', $type);
            })
            ->selectRaw('
                trackable_id as id,
                COUNT(*) as movementCount,
                SUM(quantity) as totalQuantity
            ')
            ->groupBy('trackable_id')
            ->orderBy('totalQuantity', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $ingredient = Ingredient::find($item->id);

                return [
                    'id' => $item->id,
                    'name' => $ingredient ? $ingredient->name : 'Ingrédient inconnu',
                    'type' => 'ingredient',
                    'totalQuantity' => (float) $item->totalQuantity,
                    'movementCount' => $item->movementCount,
                ];
            });

        // Top 5 des préparations les plus utilisées
        $topPreparations = StockMovement::forCompany()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('trackable_type', 'App\\Models\\Preparation')
            ->when($locationId, function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            })
            ->when($type, function ($q) use ($type) {
                $q->where('type', $type);
            })
            ->selectRaw('
                trackable_id as id,
                COUNT(*) as movementCount,
                SUM(quantity) as totalQuantity
            ')
            ->groupBy('trackable_id')
            ->orderBy('totalQuantity', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $preparation = Preparation::find($item->id);

                return [
                    'id' => $item->id,
                    'name' => $preparation ? $preparation->name : 'Préparation inconnue',
                    'type' => 'preparation',
                    'totalQuantity' => (float) $item->totalQuantity,
                    'movementCount' => $item->movementCount,
                ];
            });

        // Retourner les statistiques
        return [
            'totalMovements' => $totalMovements,
            'totalAdditions' => (float) ($totals->totalAdditions ?? 0),
            'totalWithdrawals' => (float) ($totals->totalWithdrawals ?? 0),
            'movementsByDay' => $movementsByDay,
            'topIngredients' => $topIngredients,
            'topPreparations' => $topPreparations,
        ];
    }
}
