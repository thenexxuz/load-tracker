<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Location extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'short_code',
        'name',
        'type',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'recycling_location_id',
        'latitude',
        'longitude',
        // Add any other fields specific to your app
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'recycling_location_id' => 'integer',
    ];

    /**
     * Get the associated recycling location (for distribution centers).
     */
    public function recyclingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'recycling_location_id')
                    ->where('type', 'recycling');
    }

    /**
     * Get the full formatted address.
     */
    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zip,
            $this->country,
        ]);

        return implode(', ', $parts) ?: 'Unknown address';
    }

    /**
     * Check if this location has valid coordinates.
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Calculate distance to another location.
     *
     * @param Location $other
     * @param bool $forceRecalculate Force new calculation even if cached
     * @return array
     */
    public function distanceTo(Location $other, bool $forceRecalculate = false): array
    {
        // Try to find existing distance record (bidirectional)
        $distanceRecord = LocationDistance::where(function ($q) use ($other) {
            $q->where('from_location_id', $this->id)
              ->where('to_location_id', $other->id);
        })->orWhere(function ($q) use ($other) {
            $q->where('from_location_id', $other->id)
              ->where('to_location_id', $this->id);
        })->first();

        if ($distanceRecord && !$forceRecalculate) {
            return [
                'km'              => $distanceRecord->distance_km,
                'miles'           => $distanceRecord->distance_miles,
                'duration_text'   => $distanceRecord->duration_text,
                'duration_minutes' => $distanceRecord->duration_minutes,
                'route_coords'    => $distanceRecord->route_coords ?? [],
                'source'          => 'cached',
            ];
        }

        // Calculate new distance
        $distanceData = $this->computeDistance($other);

        if (isset($distanceData['error'])) {
            Log::warning("Distance calculation failed from Location {$this->id} to {$other->id}: " . $distanceData['error']);
            return $distanceData;
        }

        // Save/update the distance record (normalized order)
        LocationDistance::updateOrCreate(
            [
                'from_location_id' => min($this->id, $other->id),
                'to_location_id'   => max($this->id, $other->id),
            ],
            [
                'distance_km'      => $distanceData['km'],
                'distance_miles'   => $distanceData['miles'],
                'duration_text'    => $distanceData['duration_text'],
                'duration_minutes' => $distanceData['duration_minutes'],
                'route_coords'     => $distanceData['route_coords'] ?? [],
                'calculated_at'    => now(),
            ]
        );

        return array_merge($distanceData, ['source' => 'calculated']);
    }

    /**
     * Internal method to compute distance (Haversine if coords available, else Mapbox).
     */
    private function computeDistance(Location $other): array
    {
        // Fast path: both have coordinates → use Haversine
        if ($this->hasCoordinates() && $other->hasCoordinates()) {
            $km = $this->haversineDistance(
                $this->latitude,
                $this->longitude,
                $other->latitude,
                $other->longitude
            );

            return [
                'km'              => round($km, 1),
                'miles'           => round($km * 0.621371, 1),
                'duration_text'   => '—', // No time estimate without directions
                'duration_minutes' => null,
                'route_coords'    => [], // No route path without API
            ];
        }

        // Fallback: full Mapbox calculation
        $token = config('services.mapbox.key');
        if (!$token) {
            return ['error' => 'Mapbox token not configured'];
        }

        $originCoords = $this->hasCoordinates()
            ? [$this->longitude, $this->latitude]
            : $this->geocodeAddress($this->fullAddress());

        if (isset($originCoords['error'])) {
            return $originCoords;
        }

        $destCoords = $other->hasCoordinates()
            ? [$other->longitude, $other->latitude]
            : $other->geocodeAddress($other->fullAddress());

        if (isset($destCoords['error'])) {
            return $destCoords;
        }

        // Mapbox Directions API
        $coordString = implode(',', $originCoords) . ';' . implode(',', $destCoords);

        $response = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/{$coordString}", [
            'access_token' => $token,
            'geometries'   => 'geojson',
            'overview'     => 'full',
        ]);

        if (!$response->successful() || empty($response['routes'])) {
            return ['error' => 'Failed to get route between locations'];
        }

        $route = $response['routes'][0];

        $meters = $route['distance'];
        $seconds = $route['duration'];

        return [
            'km'              => round($meters / 1000, 1),
            'miles'           => round(($meters / 1000) * 0.621371, 1),
            'duration_text'   => $this->secondsToHumanTime($seconds),
            'duration_minutes' => round($seconds / 60),
            'route_coords'    => $route['geometry']['coordinates'] ?? [],
        ];
    }

    /**
     * Haversine formula for straight-line distance (km).
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Geocode an address using Mapbox.
     */
    public function geocodeAddress(string $address): array
    {
        $token = config('services.mapbox.key');
        if (!$token) {
            return ['error' => 'Mapbox token not configured'];
        }

        $response = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($address) . ".json", [
            'access_token' => $token,
            'limit'        => 1,
            'types'        => 'address',
            'country'      => 'us', // adjust as needed
        ]);

        if (!$response->successful() || empty($response['features'])) {
            return ['error' => 'Failed to geocode address: ' . $address];
        }

        return $response['features'][0]['center']; // [lng, lat]
    }

    /**
     * Convert seconds to human-readable duration (e.g. "1 hr 23 min").
     */
    private function secondsToHumanTime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($hours > 0) $parts[] = $hours . ' hr';
        if ($minutes > 0) $parts[] = $minutes . ' min';

        return implode(' ', $parts) ?: '< 1 min';
    }
}
