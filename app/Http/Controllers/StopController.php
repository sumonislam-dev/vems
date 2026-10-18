<?php

namespace App\Http\Controllers;

use App\Models\Stop;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StopController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-stops', only: ['index']),
            new Middleware('permission:create-stops', only: ['store']),
        ];
    }

    /**
     * Store a newly created stop via API.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:stops,name',
            'description' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_id' => Zone::validationRule(),
        ]);

        $stop = Stop::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stop created successfully',
            'data' => $stop,
        ], 201);
    }

    /**
     * Get all stops for API.
     */
    public function index(): JsonResponse
    {
        $stops = Stop::selectable()
            ->select('id', 'name', 'description', 'latitude', 'longitude', 'zone_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stops,
        ]);
    }
}
