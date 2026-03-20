<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\LocationDistance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalculateAllRecyclingDistances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:calculate-recycling-distances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Clear all existing distance calculations
        LocationDistance::truncate();

        $dcs = Location::where('type', 'distribution_center')->get();

        foreach ($dcs as $dc) {
            $rec = $dc->recyclingLocation;
            if (! $rec) {
                continue;
            }

            // Get all recycling locations in the same short_code group as the assigned recycling location
            $recyclingGroup = Location::where('short_code', $rec->short_code)
                ->where('type', 'recycling')
                ->get();

            if ($recyclingGroup->isEmpty()) {
                continue;
            }

            $closest = null;
            $closestDistance = PHP_INT_MAX;

            // Calculate distances to all recycling locations in the group
            foreach ($recyclingGroup as $recycling) {
                $distance = $this->calculateDistance($dc->address, $recycling->address);

                if (isset($distance['error'])) {
                    Log::warning("Failed for DC {$dc->id} to Recycling {$recycling->id}: ".$distance['error']);

                    continue;
                }

                // Store the distance record
                LocationDistance::updateOrCreate(
                    ['from_location_id' => $dc->id, 'to_location_id' => $recycling->id],
                    [
                        'distance_km' => $distance['km'],
                        'distance_miles' => $distance['miles'],
                        'duration_text' => $distance['duration_text'],
                        'duration_minutes' => $distance['duration_minutes'],
                        'route_coords' => $distance['route_coords'],
                        'calculated_at' => now(),
                    ]
                );

                // Track the closest recycling location
                if ($distance['km'] < $closestDistance) {
                    $closestDistance = $distance['km'];
                    $closest = $recycling;
                }
            }

            // If the closest recycling location is different from the currently assigned one, update it
            if ($closest && $closest->id !== $rec->id) {
                $dc->update(['recycling_location_id' => $closest->id]);
                Log::info("Updated DC {$dc->id} recycling location from {$rec->id} to {$closest->id}");
            }
        }

        $this->info('All recycling distances calculated and assignments verified.');
    }

    private function calculateDistance($originAddress, $destinationAddress)
    {
        $token = config('services.mapbox.key');

        if (! $token) {
            Log::error('Mapbox token not configured');

            return ['error' => 'Mapbox token missing'];
        }

        // Geocode origin
        $originResponse = Http::get('https://api.mapbox.com/geocoding/v5/mapbox.places/'.urlencode($originAddress).'.json', [
            'access_token' => $token,
            'limit' => 1,
            'types' => 'address',
            'country' => 'us', // change if needed
        ]);

        if (! $originResponse->successful() || empty($originResponse['features'])) {
            Log::warning("Geocode failed for origin: {$originAddress}");

            return ['error' => 'Failed to geocode origin'];
        }

        $originCoords = $originResponse['features'][0]['center']; // [lng, lat]

        // Geocode destination
        $destResponse = Http::get('https://api.mapbox.com/geocoding/v5/mapbox.places/'.urlencode($destinationAddress).'.json', [
            'access_token' => $token,
            'limit' => 1,
            'types' => 'address',
            'country' => 'us',
        ]);

        if (! $destResponse->successful() || empty($destResponse['features'])) {
            Log::warning("Geocode failed for destination: {$destinationAddress}");

            return ['error' => 'Failed to geocode destination'];
        }

        $destCoords = $destResponse['features'][0]['center'];

        // Get driving directions
        $coords = implode(',', $originCoords).';'.implode(',', $destCoords); // lng1,lat1;lng2,lat2

        $directionsResponse = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/{$coords}", [
            'access_token' => $token,
            'geometries' => 'geojson',
            'overview' => 'full',
        ]);

        if (! $directionsResponse->successful() || empty($directionsResponse['routes'])) {
            Log::warning("Directions failed between {$originAddress} and {$destinationAddress}");

            return ['error' => 'Failed to get route'];
        }

        $route = $directionsResponse['routes'][0];

        $meters = $route['distance'];
        $seconds = $route['duration'];

        return [
            'km' => round($meters / 1000, 1),
            'miles' => round(($meters / 1000) * 0.621371, 1),
            'duration_text' => $this->secondsToHumanTime($seconds),
            'duration_minutes' => round($seconds / 60),
            'route_coords' => $route['geometry']['coordinates'] ?? [],
        ];
    }

    private function secondsToHumanTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' hr';
        }
        if ($minutes > 0) {
            $parts[] = $minutes.' min';
        }

        return implode(' ', $parts) ?: '< 1 min';
    }
}
