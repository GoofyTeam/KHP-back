<?php

namespace App\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Loss;
use App\Services\LossService;
use Illuminate\Http\Request;

class LossController extends Controller
{
    public function index()
    {
        return Loss::with(['ingredient', 'location', 'company'])->paginate(25);
    }

    public function store(Request $request, LossService $service)
    {
        $payload = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'ingredient_type' => 'required|string',
            'ingredient_id' => 'required|integer',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string',
            'reason' => 'nullable|string',
            'comment' => 'nullable|string',
        ]);

        $loss = $service->createLoss($payload);

        return response()->json($loss->load(['ingredient', 'location']), 201);
    }
}
