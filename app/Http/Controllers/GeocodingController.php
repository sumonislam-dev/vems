<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Proxies free-text location search to OpenStreetMap's Nominatim, server
 * side. Nominatim's public instance requires a valid identifying User-Agent
 * and a strict 1 req/sec rate limit (see its usage policy) — a browser can't
 * set its own User-Agent and many real users sharing one office IP can
 * easily blow past that limit, so calling it straight from the frontend
 * (the previous approach) made location search fail unpredictably for
 * everyone on that network. Routing it through here lets us identify the
 * app properly and apply our own, more generous per-user throttle instead.
 */
class GeocodingController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:255',
            'countrycodes' => 'nullable|string|max:255',
            'viewbox' => 'nullable|string|max:255',
        ]);

        $response = Http::withHeaders([
            'User-Agent' => config('app.name').' ('.config('app.url').')',
        ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
            'format' => 'json',
            'q' => $validated['q'],
            'limit' => 8,
            'addressdetails' => 1,
            'countrycodes' => $validated['countrycodes'] ?? null,
            'viewbox' => $validated['viewbox'] ?? null,
            'bounded' => 0,
        ]);

        if ($response->failed()) {
            return response()->json([]);
        }

        return response()->json($response->json());
    }
}
