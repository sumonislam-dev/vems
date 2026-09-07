<?php

namespace App\Services;

use App\Models\Factory;
use App\Models\Stop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a raw lat/lng into a readable place name for attendance
 * check-in/break events. Cheapest-first: a known factory or route stop
 * within MATCH_RADIUS_METERS resolves for free with no network call;
 * only an unmatched point falls through to Nominatim.
 */
class LocationResolver
{
    private const MATCH_RADIUS_METERS = 150;

    /**
     * Full resolution: a known factory/stop match, else Nominatim. Nominatim
     * is a network call — callers on the request/response cycle should
     * prefer matchKnown() and queue the rest (see ResolveAttendanceEventLocation).
     *
     * @return array{location_name: ?string, factory_id: ?int, source: ?string}
     */
    public function resolve(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['location_name' => null, 'factory_id' => null, 'source' => null];
        }

        $match = $this->matchKnown($lat, $lng);

        if ($match['factory_id'] || $match['location_name']) {
            return ['location_name' => $match['location_name'], 'factory_id' => $match['factory_id'], 'source' => $match['source']];
        }

        return [
            'location_name' => $this->reverseGeocode($lat, $lng),
            'factory_id' => null,
            'source' => 'nominatim',
        ];
    }

    /**
     * Nearest known factory/stop — free, local, no network call. `location_name`/
     * `factory_id`/`source` are only populated when the nearest point is within
     * MATCH_RADIUS_METERS; `distance_meters` is always populated (when at least
     * one factory/stop has coordinates), so callers can apply a looser threshold
     * of their own — e.g. geofencing a check-in against a wider radius than the
     * one used to decide whether to *name* the location.
     *
     * @return array{location_name: ?string, factory_id: ?int, source: ?string, distance_meters: ?float}
     */
    public function matchKnown(float $lat, float $lng): array
    {
        $candidates = collect();

        Factory::query()->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'name', 'latitude', 'longitude'])
            ->each(function ($factory) use ($candidates) {
                $candidates->push(['name' => $factory->name, 'factory_id' => $factory->id, 'source' => 'factory', 'lat' => (float) $factory->latitude, 'lng' => (float) $factory->longitude]);
            });

        Stop::query()->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'name', 'latitude', 'longitude'])
            ->each(function ($stop) use ($candidates) {
                $candidates->push(['name' => $stop->name, 'factory_id' => null, 'source' => 'stop', 'lat' => (float) $stop->latitude, 'lng' => (float) $stop->longitude]);
            });

        $nearest = $candidates
            ->map(fn ($c) => $c + ['distance' => $this->haversineMeters($lat, $lng, $c['lat'], $c['lng'])])
            ->sortBy('distance')
            ->first();

        if (! $nearest) {
            return ['location_name' => null, 'factory_id' => null, 'source' => null, 'distance_meters' => null];
        }

        $withinMatchRadius = $nearest['distance'] <= self::MATCH_RADIUS_METERS;

        return [
            'location_name' => $withinMatchRadius ? $nearest['name'] : null,
            'factory_id' => $withinMatchRadius ? $nearest['factory_id'] : null,
            'source' => $withinMatchRadius ? $nearest['source'] : null,
            'distance_meters' => round($nearest['distance'], 1),
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function reverseGeocode(float $lat, float $lng): ?string
    {
        // Round to ~11m precision so nearby lookups share one cached, one
        // rate-limited, request against Nominatim's 1 req/sec usage policy.
        $cacheKey = 'geocode:nominatim:'.round($lat, 4).','.round($lng, 4);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($lat, $lng) {
            try {
                $response = Http::timeout(3)
                    ->withHeaders(['User-Agent' => config('services.nominatim.user_agent')])
                    ->get(config('services.nominatim.base_url').'/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $lat,
                        'lon' => $lng,
                        'zoom' => 18,
                        'addressdetails' => 0,
                    ]);

                if (! $response->successful()) {
                    Log::warning('Nominatim reverse geocode rejected', [
                        'lat' => $lat,
                        'lng' => $lng,
                        'status' => $response->status(),
                        'body' => str($response->body())->limit(200)->toString(),
                    ]);

                    return null;
                }

                return $response->json('display_name');
            } catch (\Throwable $e) {
                Log::warning('Nominatim reverse geocode failed', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
