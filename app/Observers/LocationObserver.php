<?php

namespace App\Observers;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationObserver
{
    public function saving(Location $location): void
    {
        // Only allow distribution centers to have a recycling location
        if ($location->recycling_location_id !== null) {
            if ($location->type !== 'distribution_center') {
                throw new \Exception('Only distribution centers can be assigned a recycling location.');
            }
        }

        // Skip if no address or if lat/lng already set and address not changed
        if (! $location->address || ($location->latitude && $location->longitude && ! $location->isDirty(['address', 'city', 'state', 'zip', 'country']))) {
            Log::info("Skipped for Location {$location->id}: {$location->fullAddress()}");

            return;
        }

        $cacheKey = 'geocode:'.md5($location->fullAddress());

        // Try to get from cache first (long TTL: 1 year)
        $cached = Cache::get($cacheKey);

        if ($cached) {
            $location->latitude = $cached['lat'];
            $location->longitude = $cached['lng'];
            Log::info("Used cached geocode for Location {$location->id}: {$location->fullAddress()}");

            return;
        }

        // Call Mapbox Geocoding API
        $token = config('services.mapbox.key');
        if (! $token) {
            Log::warning("Mapbox token missing - cannot geocode Location {$location->id}");

            return;
        }

        $address = urlencode($location->fullAddress());

        $response = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$address}.json", [
            'access_token' => $token,
            'limit' => 1,
            'types' => 'address',
            'country' => strtolower($location->country),
        ]);

        if (! $response->successful() || empty($response['features'])) {
            Log::warning("Geocoding failed for Location {$location->id}: {$location->fullAddress()}");

            return;
        }

        $feature = $response['features'][0];
        $coordinates = $feature['center']; // [lng, lat]

        $lat = $coordinates[1];
        $lng = $coordinates[0];

        // Store in model
        $location->latitude = $lat;
        $location->longitude = $lng;

        // Cache for 1 year (longest practical time - addresses rarely change)
        Cache::put($cacheKey, [
            'lat' => $lat,
            'lng' => $lng,
        ], now()->addYear());

        Log::info("Geocoded Location {$location->id}: {$location->fullAddress()} → {$lat}, {$lng}");
    }

    public function saved(Location $location): void
    {
        // Optional: ensure inverse relationship consistency
        if ($location->recycling_location_id !== null) {
            $recyclingLoc = Location::find($location->recycling_location_id);
            if ($recyclingLoc && $recyclingLoc->recycling_location_id !== $location->id) {
                $recyclingLoc->updateQuietly([
                    'recycling_location_type' => Location::class,
                    'recycling_location_id' => $location->id,
                ]);
            }
        }
    }
}
