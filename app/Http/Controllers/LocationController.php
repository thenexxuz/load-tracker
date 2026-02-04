<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\LocationDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use League\Csv\Reader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Log;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::query()
            ->with('recyclingLocation:id,short_code,name')
            ->when(request('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%")
                ->orWhere('type', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations,
            'filters' => request()->only('search'),
        ]);
    }

    public function create()
    {
        $availableRecyclingLocations = Location::where('type', 'recycling')
            ->select('id', 'short_code', 'name')
            ->get();

        return Inertia::render('Admin/Locations/Create', [
            'availableRecyclingLocations' => $availableRecyclingLocations,
        ]);
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'short_code' => ['required', 'string', 'max:20', 'unique:locations,short_code'],
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:255'],
            'expected_arrival_time' => ['nullable', 'string'],
            'type' => ['required', 'in:pickup,distribution_center,recycling'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
            'recycling_location_id' => ['nullable', 'exists:locations,id'],
        ]);
        $validated['country'] = $validated['country'] ?? 'US';

        // Enforce business rule: only distribution centers can have a recycling location
        if ($validated['type'] !== 'distribution_center') {
            $validated['recycling_location_id'] = null;
        }

        // Create the location
        $location = Location::create($validated);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location created successfully.');
    }

    public function show(Location $location)
    {
        $location->load('recyclingLocation');

        return Inertia::render('Admin/Locations/Show', [
            'location' => $location,
        ]);
    }

    public function edit(Location $location)
    {
        $location->load('recyclingLocation:id,short_code,name');

        $availableRecyclingLocations = Location::where('type', 'recycling')
            ->select('id', 'short_code', 'name')
            ->get();

        return Inertia::render('Admin/Locations/Edit', [
            'location' => $location,
            'availableRecyclingLocations' => $availableRecyclingLocations,
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'short_code' => 'required|string|max:20|unique:locations,short_code,'.$location->id,
            'name' => 'nullable|string|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip' => 'nullable|string|max:10',
            'country' => 'string|max:2',
            'email' => ['nullable', 'email', 'max:255'],
            'expected_arrival_time' => ['nullable', 'string'],
            'type' => 'required|in:pickup,distribution_center,recycling',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean',
            'recycling_location_id' => 'nullable',
        ]);

        $location->update($validated);

        // Helper to check if address changed
        $addressFields = ['address', 'city', 'state', 'zip', 'country'];
        $addressChanged = $location->wasChanged($addressFields);

        if ($location->type === 'distribution_center') {
            // For DC: check if recycling changed or address changed
            $recyclingChanged = $location->wasChanged('recycling_location_id') || $oldRecyclingId !== $location->recycling_location_id;

            if ($recyclingChanged || $addressChanged) {
                $this->recalculateDistanceForDc($location);
            }
        } elseif ($location->type === 'recycling') {
            // For recycling: if address changed, recalculate for all linked DCs
            if ($addressChanged) {
                $linkedDcs = Location::where('type', 'distribution_center')
                    ->where('recycling_location_id', $location->id)
                    ->get();

                foreach ($linkedDcs as $dc) {
                    $this->recalculateDistanceForDc($dc);
                }
            }
        }

        return redirect()->route('admin.locations.show', $location)->with('success', 'Location updated successfully.');
    }

    /**
     * Recalculate and update distance for a given DC
     */
    private function recalculateDistanceForDc(Location $dc)
    {
        $rec = $dc->recyclingLocation;

        if (!$rec) {
            // Optional: delete any existing distance if recycling removed
            LocationDistance::where('dc_id', $dc->id)->delete();
            return;
        }

        $distanceData = $this->calculateDistance($dc->address, $rec->address);

        if (isset($distanceData['error'])) {
            \Log::warning("Failed to recalculate distance for DC {$dc->id} to Recycling {$rec->id}: " . $distanceData['error']);
            return;
        }

        LocationDistance::updateOrCreate(
            [
                'dc_id' => $dc->id,
                'recycling_id' => $rec->id,
            ],
            [
                'distance_km' => $distanceData['km'],
                'distance_miles' => $distanceData['miles'],
                'duration_text' => $distanceData['duration_text'],
                'duration_minutes' => $distanceData['duration_minutes'],
                'route_coords' => $distanceData['route_coords'] ?? [],
                'calculated_at' => now(),
            ]
        );
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location deleted successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt,tsv|max:10240', // max 10MB
        ]);

        $file = $request->file('file');

        try {
            $csv = Reader::createFromPath($file->getRealPath(), 'r');
            $csv->setDelimiter("\t");
            $csv->setHeaderOffset(0); // First row = headers

            $records = $csv->getRecords();

            $imported = 0;
            $errors = [];

            foreach ($records as $offset => $row) {
                $data = (array) $row;

                // Validate each row
                $validator = Validator::make($data, [
                    'short_code' => ['required', 'string', 'max:20'],
                    'name' => ['nullable', 'string', 'max:255'],
                    'address' => ['required', 'string'],
                    'city' => ['nullable', 'string', 'max:100'],
                    'state' => ['nullable', 'string', 'size:2'],
                    'zip' => ['nullable', 'string', 'max:10'],
                    'country' => ['required', 'string', 'size:2'],
                    'type' => ['required', 'in:pickup,distribution_center,recycling'],
                    'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                    'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                    'is_active' => ['nullable', 'boolean'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'expected_arrival_time' => ['nullable', 'string'],
                    'recycling_short_code' => ['nullable', 'string', 'max:20'],
                ]);

                if ($validator->fails()) {
                    $errors[] = 'Row '.($offset + 2).': '.implode(', ', $validator->errors()->all());

                    continue;
                }

                // Normalize country
                $data['country'] = strtoupper($data['country'] ?? 'US');

                // Handle recycling relationship (only for distribution_center)
                $recyclingLocationId = null;
                if ($data['type'] === 'distribution_center' && ! empty($data['recycling_short_code'])) {
                    $recyclingLocation = Location::where('short_code', $data['recycling_short_code'])->first();
                    if ($recyclingLocation) {
                        $recyclingLocationId = $recyclingLocation->id;
                    } else {
                        $errors[] = 'Row '.($offset + 2).": Recycling location with short_code '{$data['recycling_short_code']}' not found.";

                        continue;
                    }
                }

                // Business rule: force null if not distribution_center
                if ($data['type'] !== 'distribution_center') {
                    $recyclingLocationId = null;
                }

                // Prepare data for create/update
                $importData = [
                    'guid' => (string) Str::uuid(),
                    'name' => $data['name'] ?? null,
                    'address' => $data['address'],
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'zip' => $data['zip'] ?? null,
                    'country' => $data['country'],
                    'type' => $data['type'],
                    'latitude' => $data['latitude'] ? (float) $data['latitude'] : null,
                    'longitude' => $data['longitude'] ? (float) $data['longitude'] : null,
                    'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'email' => $data['email'] ?? null,
                    'expected_arrival_time' => $data['expected_arrival_time'] ?? null,
                    'recycling_location_id' => $recyclingLocationId,
                ];

                // Create or update based on short_code
                Location::updateOrCreate(
                    ['short_code' => $data['short_code']],
                    $importData
                );

                $imported++;
            }

            if ($imported === 0 && ! empty($errors)) {
                return back()->withErrors(['file' => 'No valid rows imported. Errors: '.implode('; ', $errors)]);
            }

            $message = "$imported location(s) imported/updated successfully.";
            if (! empty($errors)) {
                $message .= ' '.count($errors).' rows skipped due to errors.';
            }

            return redirect()->route('admin.locations.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to process file: '.$e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        $query = Location::query()
            ->with('recyclingLocation:id,short_code') // only need short_code
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%"));

        $locations = $query->get([
            'short_code',
            'name',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'type',
            'latitude',
            'longitude',
            'is_active',
            'email',
            'expected_arrival_time',
            'recycling_location_id', // we'll replace with short_code below
        ]);

        // Transform to include recycling_short_code instead of ID
        $exportData = $locations->map(function ($loc) {
            return [
                'short_code' => $loc->short_code,
                'name' => $loc->name ?? '',
                'address' => $loc->address,
                'city' => $loc->city ?? '',
                'state' => $loc->state ?? '',
                'zip' => $loc->zip ?? '',
                'country' => $loc->country,
                'type' => $loc->type,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
                'is_active' => $loc->is_active ? '1' : '0',
                'email' => $loc->email ?? '',
                'expected_arrival_time' => $loc->expected_arrival_time ?? '',
                'recycling_short_code' => $loc->recyclingLocation?->short_code ?? '', // ← Added!
            ];
        });

        // Generate TSV content
        $headers = [
            'short_code', 'name', 'address', 'city', 'state', 'zip', 'country',
            'type', 'latitude', 'longitude', 'is_active', 'email', 'expected_arrival_time',
            'recycling_short_code', // Make sure importer knows this is the short code
        ];

        $tsv = implode("\t", $headers)."\n";

        foreach ($exportData as $row) {
            $tsv .= implode("\t", array_map(function ($value) {
                // Escape tabs and quotes, wrap in quotes if needed
                $value = str_replace(["\t", "\n", "\r"], ['\\t', '\\n', '\\r'], $value);
                if (str_contains($value, "\t") || str_contains($value, '"') || str_contains($value, "\n")) {
                    $value = '"'.str_replace('"', '""', $value).'"';
                }

                return $value;
            }, $row))."\n";
        }

        $filename = 'locations_export_'.now()->format('Y-m-d_His').'.tsv';

        return response($tsv)
            ->header('Content-Type', 'text/tab-separated-values')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    public function recyclingDistances(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $recyclingId = $request->input('recycling_id');

        $query = Location::where('type', 'distribution_center')
            ->with('recyclingLocation:id,short_code,address');

        // Filter by specific recycling location
        if ($recyclingId === 'none') {
            $query->whereNull('recycling_location_id');
        } elseif ($recyclingId && is_numeric($recyclingId)) {
            $query->where('recycling_location_id', $recyclingId);
        }
        // else: no filter → show all

        $dcLocations = $query->paginate($perPage);

        $distances = $dcLocations->through(function ($dc) {
            $rec = $dc->recyclingLocation;

            if (!$rec) {
                return [
                    'dc_id' => $dc->id,
                    'dc_short_code' => $dc->short_code,
                    'rec_id' => null,
                    'rec_short_code' => null,
                    'distance_km' => null,
                    'distance_miles' => null,
                    'duration_text' => 'No recycling assigned',
                    'route_coords' => [],
                ];
            }

            $distanceRecord = LocationDistance::where('dc_id', $dc->id)
                ->where('recycling_id', $rec->id)
                ->first();

            return [
                'dc_id' => $dc->id,
                'dc_short_code' => $dc->short_code,
                'rec_id' => $rec->id,
                'rec_short_code' => $rec->short_code,
                'distance_km' => $distanceRecord?->distance_km,
                'distance_miles' => $distanceRecord?->distance_miles,
                'duration_text' => $distanceRecord?->duration_text,
                'route_coords' => $distanceRecord?->route_coords ?? [],
            ];
        });

        return Inertia::render('Admin/Locations/RecyclingDistance', [
            'distances' => $distances,
            'recycling_locations' => Location::where('type', 'recycling')
                ->select('id', 'short_code')
                ->orderBy('short_code')
                ->get(),
        ]);
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
        $coords = implode(',',$originCoords).';'. implode(',', $destCoords); // lng1,lat1;lng2,lat2

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

    public function recyclingDistanceMap($dcId, $recId)
    {
        $dc = Location::findOrFail($dcId);
        $rec = Location::findOrFail($recId);

        // Ensure they are the correct types (optional safety)
        if ($dc->type !== 'distribution_center' || $rec->type !== 'recycling') {
            abort(404, 'Invalid location types');
        }

        // Get the route coordinates (reuse your existing logic)
        $distance = $this->calculateDistance($dc->address, $rec->address);

        if (isset($distance['error'])) {
            return Inertia::render('Admin/Locations/RecyclingDistanceMap', [
                'error' => $distance['error'],
                'dc' => $dc->only(['id', 'short_code', 'address']),
                'rec' => $rec->only(['id', 'short_code', 'address']),
            ]);
        }

        return Inertia::render('Admin/Locations/RecyclingDistanceMap', [
            'dc' => $dc->only(['id', 'short_code', 'address']),
            'rec' => $rec->only(['id', 'short_code', 'address']),
            'dc_sc' => $dc->short_code,
            'rec_sc' => $rec->short_code,
            'distance_km' => $distance['km'] ?? null,
            'distance_miles' => $distance['miles'] ?? null,
            'duration_text' => $distance['duration_text'] ?? null,
            'route_coords' => $distance['route_coords'] ?? [],  // [[lng, lat], ...]
            'mapbox_token' => config('services.mapbox.key'),
        ]);
    }
}
