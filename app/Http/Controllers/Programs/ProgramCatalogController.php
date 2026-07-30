<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;

class ProgramCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Program::where('is_active', true)->orderBy('name')->get());
    }
}
