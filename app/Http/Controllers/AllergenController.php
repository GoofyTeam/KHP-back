<?php

namespace App\Http\Controllers;

use App\Enums\Allergen;
use Illuminate\Http\JsonResponse;

class AllergenController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Allergen::values());
    }
}
