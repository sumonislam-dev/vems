# Google Geocoding Implementation Guide

Implementation guide for resolving `Check_In_Location` and `Break1–3_Start_Location` on the Attendance Reports page from raw GPS coordinates into a readable place name, using a **hybrid** approach: match against factories/stops already stored in VEMS first, and call the Google Maps Geocoding API only for the rare point that doesn't match.

This document covers: getting a Google API key, configuring it in Laravel, the code to write, and cost/security best practices. No code has been changed yet — this is the plan to implement it.

---

## 1. Get a Google Maps API key

1. Go to [Google Cloud Console](https://console.cloud.google.com/) and sign in with the account that should own billing for this.
2. Create a new project (or pick an existing one) — top-left project dropdown → **New Project**.
3. Enable the API: **APIs & Services → Library**, search **"Geocoding API"**, click it, click **Enable**.
4. Enable billing: **Billing → Link a billing account**. Google requires an active billing account even to use the free monthly allowance — without it, requests return `REQUEST_DENIED`.
5. Create the key: **APIs & Services → Credentials → Create Credentials → API key**. Copy the generated key.
6. **Restrict the key immediately** (Credentials → click the key → Restrictions):
   - **Application restriction:** *IP addresses* — set it to your production server's outbound IP. This is a server-side key (called from Laravel, never from the browser), so it should never be an unrestricted or browser-key type.
   - **API restriction:** limit it to *Geocoding API* only, so a leaked key can't be used against other Google APIs on your account.
7. Set a budget alert: **Billing → Budgets & alerts → Create budget**. Pick a small monthly threshold (e.g. $5) so you get an email if usage ever spikes unexpectedly.

Google's free monthly usage credit and per-request pricing are set by Google and change over time — check the current numbers on Google's Maps Platform pricing page before budgeting. The hybrid design below is what keeps actual paid calls rare regardless of what the current rate is.

---

## 2. Configure it in Laravel

Add to `.env` (never commit this file):

```
GOOGLE_MAPS_API_KEY=your-key-here
```

Add to `config/services.php`:

```php
'google_maps' => [
    'key' => env('GOOGLE_MAPS_API_KEY'),
],
```

Reading it via `config('services.google_maps.key')` (rather than `env()` directly in application code) means it still works after `config:cache` in production.

---

## 3. The hybrid resolver

New class: `app/Services/LocationResolver.php`

```php
<?php

namespace App\Services;

use App\Models\Factory;
use App\Models\Stop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationResolver
{
    private const MATCH_RADIUS_METERS = 150;

    /**
     * @return array{location_name: ?string, factory_id: ?int, source: ?string}
     */
    public function resolve(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['location_name' => null, 'factory_id' => null, 'source' => null];
        }

        if ($factory = $this->nearest(Factory::query()->whereNotNull('latitude')->whereNotNull('longitude')->get(), $lat, $lng)) {
            return ['location_name' => $factory->name, 'factory_id' => $factory->id, 'source' => 'factory'];
        }

        if ($stop = $this->nearest(Stop::query()->whereNotNull('latitude')->whereNotNull('longitude')->get(), $lat, $lng)) {
            return ['location_name' => $stop->name, 'factory_id' => null, 'source' => 'stop'];
        }

        return [
            'location_name' => $this->reverseGeocode($lat, $lng),
            'factory_id' => null,
            'source' => 'google',
        ];
    }

    /**
     * Nearest record within MATCH_RADIUS_METERS, or null if nothing is close enough.
     */
    private function nearest($points, float $lat, float $lng)
    {
        return $points
            ->map(fn ($point) => [
                'point' => $point,
                'distance' => $this->haversineMeters($lat, $lng, (float) $point->latitude, (float) $point->longitude),
            ])
            ->filter(fn ($p) => $p['distance'] <= self::MATCH_RADIUS_METERS)
            ->sortBy('distance')
            ->first()['point'] ?? null;
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
        $key = config('services.google_maps.key');

        if (! $key) {
            return null;
        }

        // Round to ~11m precision so nearby lookups share one cached, one billed, result.
        $cacheKey = 'geocode:'.round($lat, 4).','.round($lng, 4);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($lat, $lng, $key) {
            try {
                $response = Http::timeout(3)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => "{$lat},{$lng}",
                    'key' => $key,
                ]);

                $result = $response->json('results.0.formatted_address');

                return $response->successful() ? $result : null;
            } catch (\Throwable $e) {
                Log::warning('Google reverse geocode failed', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
```

Notes on that class:
- **Radius (`MATCH_RADIUS_METERS`):** 150m is a starting point, not a fixed rule — tighten it if two factories sit close together, loosen it if GPS drift at a large site causes real on-site check-ins to miss.
- **`->get()` on every resolve() call** is fine at VEMS's current factory/stop count (dozens, not thousands). If that list grows large, cache it in memory per request instead of querying every time.
- **Caching the Google result** by rounded coordinate is what keeps this affordable — the same break spot is only ever billed once in 30 days, not once per event.
- **Failure is silent by design** — a timeout, quota error, or bad key returns `null`, never throws. The event still gets its lat/lng saved either way; only the friendly name is missing, and the frontend already falls back to showing raw coordinates in that case.

---

## 4. Wire it into `AttendanceController`

In `checkIn()`, `breakStart()`, and `breakEnd()` — none of which currently capture a place name, only `latitude`/`longitude` — resolve and attach it after the event is created:

```php
$location = app(\App\Services\LocationResolver::class)->resolve(
    $validated['latitude'] ?? null,
    $validated['longitude'] ?? null,
);

$event->update([
    'location_name' => $location['location_name'],
    'factory_id' => $location['factory_id'],
]);
```

(`checkOut()` already lets the user pick a factory manually — call the resolver there too, but only to *fill in* `location_name`/`factory_id` when the user didn't supply one, never to override their manual pick.)

---

## 5. Testing without spending money

Never let a real Google call happen in tests — fake the HTTP client:

```php
use Illuminate\Support\Facades\Http;

it('falls back to google only when no factory or stop matches', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [['formatted_address' => 'Some Street, Dhaka']],
        ]),
    ]);

    // ...assert LocationResolver::resolve() returns that address
    // when given coordinates far from every factory/stop.
});
```

`Http::fake()` intercepts the call entirely — no network request, no charge, deterministic in CI.

---

## 6. Cost & security checklist

- [ ] API key restricted to your server's IP and to the Geocoding API only (step 1.6).
- [ ] Billing budget alert set (step 1.7).
- [ ] Hybrid order is factory → stop → Google, never Google-first — this is what keeps paid calls rare.
- [ ] Google results cached by rounded coordinate (30 days is a reasonable starting TTL).
- [ ] `.env` never committed; key rotated immediately if it ever leaks (Credentials → Regenerate key).
- [ ] Geocoding failure never blocks a check-in/break action — confirm the `try/catch` in `reverseGeocode()` stays in place.
