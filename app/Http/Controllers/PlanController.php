<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Plan::where('is_active', true)->orderBy('price')->get(['id', 'name', 'slug', 'price', 'billing_cycle', 'features', 'max_programs', 'has_coach_access'])
        );
    }
}
