<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\LocationDistance;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Command;
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
        $dcs = Location::where('type', 'distribution_center')->get();

        foreach ($dcs as $dc) {
            $rec = $dc->recyclingLocation;
            if (!$rec)
                continue;

            $distance = $this->calculateDistance($dc->address, $rec->address);

            if (isset($distance['error'])) {
                Log::warning("Failed for DC {$dc->id}: " . $distance['error']);
                continue;
            }

            LocationDistance::updateOrCreate(
                ['dc_id' => $dc->id, 'recycling_id' => $rec->id],
                [
                    'distance_km' => $distance['km'],
                    'distance_miles' => $distance['miles'],
                    'duration_text' => $distance['duration_text'],
                    'duration_minutes' => $distance['duration_minutes'],
                    'route_coords' => $distance['route_coords'],
                    'calculated_at' => now(),
                ]
            );
        }

        $this->info('All recycling distances calculated.');
    }

    private function calculateDistance($originAddress, $destinationAddress)
    {
        $token = config('services.mapbox.key');

        if (!$token) {
            Log::error('Mapbox token not configured');
            return ['error' => 'Mapbox token missing'];
        }

        // Geocode origin
        $originResponse = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($originAddress) . ".json", [
            'access_token' => $token,
            'limit' => 1,
            'types' => 'address',
            'country' => 'us', // change if needed
        ]);

        if (!$originResponse->successful() || empty($originResponse['features'])) {
            Log::warning("Geocode failed for origin: {$originAddress}");
            return ['error' => 'Failed to geocode origin'];
        }

        $originCoords = $originResponse['features'][0]['center']; // [lng, lat]


        // Geocode destination
        $destResponse = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($destinationAddress) . ".json", [
            'access_token' => $token,
            'limit' => 1,
            'types' => 'address',
            'country' => 'us',
        ]);

        if (!$destResponse->successful() || empty($destResponse['features'])) {
            Log::warning("Geocode failed for destination: {$destinationAddress}");
            return ['error' => 'Failed to geocode destination'];
        }

        $destCoords = $destResponse['features'][0]['center'];

        // Get driving directions
        $coords = implode(',', $originCoords) . ';' . implode(',', $destCoords); // lng1,lat1;lng2,lat2

        $directionsResponse = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/{$coords}", [
            'access_token' => $token,
            'geometries' => 'geojson',
            'overview' => 'full',
        ]);

        if (!$directionsResponse->successful() || empty($directionsResponse['routes'])) {
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
        if ($hours > 0)
            $parts[] = $hours . ' hr';
        if ($minutes > 0)
            $parts[] = $minutes . ' min';

        return implode(' ', $parts) ?: '< 1 min';
    }
}
