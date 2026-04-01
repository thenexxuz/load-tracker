<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\LocationDistance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class LocationController extends Controller
{
    /**
     * Display a listing of locations.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:25',
            'search' => 'nullable|string|max:500',
        ]);

        $query = Location::query()
            ->with('recyclingLocation:id,short_code');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('zip', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('short_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $locations = $query->paginate($validated['per_page'] ?? 15);

        $locations->getCollection()->transform(function (Location $location) {
            return [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
                'type' => $location->type,
                'address' => $location->address,
                'city' => $location->city,
                'state' => $location->state,
                'zip' => $location->zip,
                'country' => $location->country,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'created_at' => $location->created_at,
                'has_notes' => $location->notes()->exists(),
            ];
        });

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations,
        ]);
    }

    /**
     * Show the form for creating a new location.
     */
    public function create()
    {
        $recyclingLocations = Location::where('type', 'recycling')
            ->orderBy('short_code')
            ->get(['id', 'guid', 'short_code', 'name']);

        return Inertia::render('Admin/Locations/Create', [
            'availableRecyclingLocations' => $recyclingLocations->map(fn (Location $location) => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
            ])->values(),
        ]);
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'short_code' => [
                'required',
                'string',
                'max:50',
            ],
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:distribution_center,recycling,pickup',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean',
            'recycling_location_id' => 'nullable|uuid|exists:locations,guid',
            'emails' => 'nullable|string', // comma-separated list
            'expected_arrival_time' => 'nullable|date_format:H:i',
        ]);

        // Parse comma-separated emails into array
        $emails = $validated['emails']
            ? array_filter(array_map('trim', explode(',', $validated['emails'])))
            : [];

        // Store as JSON array
        $validated['emails'] = $emails;
        $validated['expected_arrival_time'] = $this->normalizeExpectedArrivalTime(
            $validated['expected_arrival_time'] ?? null
        );
        $validated['recycling_location_id'] = $this->resolveLocationRecordIdFromGuid($validated['recycling_location_id'] ?? null);

        // Create location
        $location = Location::create($validated);

        return redirect()
            ->route('admin.locations.index')
            ->with('success', 'Location created successfully.');
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location)
    {
        $location->load('recyclingLocation');

        $routeData = null;
        if ($location->recyclingLocation) {
            $routeData = LocationDistance::where('from_location_id', $location->id)
                ->where('to_location_id', $location->recyclingLocation->id)
                ->select('distance_km', 'distance_miles', 'duration_text', 'duration_minutes', 'route_coords')
                ->first();
        }

        // Load shipments for this location as pickup point
        $shipments = $location->pickupShipments()
            ->with([
                'carrier:id,name,short_code',
                'trailer:id,number,carrier_id',
                'loanedFromTrailer:id,number,carrier_id',
            ])
            ->whereRaw("LOWER(status) NOT IN ('delivered', 'cancelled')")
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->groupBy(fn ($shipment): string => filled($shipment->consolidation_number)
                ? 'consolidation:'.$shipment->consolidation_number
                : 'shipment:'.$shipment->getKey())
            ->map(fn ($shipmentGroup) => filled($shipmentGroup->first()?->consolidation_number)
                ? $shipmentGroup->sortBy('shipment_number', SORT_NATURAL)->values()
                : $shipmentGroup->values())
            ->values()
            ->flatten(1)
            ->values()
            ->map(function ($shipment) {
                $trailer = $shipment->getRelation('trailer');

                return [
                    'id' => $shipment->guid,
                    'shipment_number' => $shipment->shipment_number,
                    'bol' => $shipment->bol,
                    'status' => $shipment->status,
                    'consolidation_number' => $shipment->consolidation_number,
                    'carrier_id' => $shipment->carrier_id,
                    'carrier_name' => $shipment->carrier?->name,
                    'trailer_id' => $shipment->trailer_id,
                    'trailer_number' => $trailer?->number,
                    'loaned_from_trailer_id' => $shipment->loaned_from_trailer_id,
                    'drop_date' => $shipment->drop_date,
                    'pickup_date' => $shipment->pickup_date,
                    'delivery_date' => $shipment->delivery_date,
                ];
            });

        // Get all carriers and trailers for the quick edit dropdowns
        $carriers = \App\Models\Carrier::where('is_active', true)
            ->orderBy('name')
            ->select('id', 'name', 'short_code')
            ->get();

        $trailers = \App\Models\Trailer::where('is_active', true)
            ->with('carrier:id,name')
            ->orderBy('number')
            ->select('id', 'number', 'carrier_id', 'status')
            ->get();

        return Inertia::render('Admin/Locations/Show', [
            'location' => [
                'id' => $location->guid,
                'notable_id' => $location->getKey(),
                'short_code' => $location->short_code,
                'name' => $location->name,
                'type' => $location->type,
                'address' => $location->address,
                'city' => $location->city,
                'state' => $location->state,
                'zip' => $location->zip,
                'country' => $location->country,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'emails' => $location->emails,
                'expected_arrival_time' => $this->formatExpectedArrivalTime($location->expected_arrival_time),
                'is_active' => $location->is_active,
                'recycling_location' => $location->recyclingLocation ? [
                    'id' => $location->recyclingLocation->guid,
                    'short_code' => $location->recyclingLocation->short_code,
                    'name' => $location->recyclingLocation->name,
                    'latitude' => $location->recyclingLocation->latitude,
                    'longitude' => $location->recyclingLocation->longitude,
                ] : null,
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
            ],
            'shipments' => $shipments,
            'carriers' => $carriers,
            'trailers' => $trailers,
            'routeData' => $routeData,
            'mapbox_token' => config('services.mapbox.key'),
        ]);
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Location $location)
    {
        $recyclingLocations = Location::where('type', 'recycling')
            ->orderBy('short_code')
            ->get();

        return Inertia::render('Admin/Locations/Edit', [
            'availableRecyclingLocations' => $recyclingLocations->map(fn (Location $recyclingLocation) => [
                'id' => $recyclingLocation->guid,
                'short_code' => $recyclingLocation->short_code,
                'name' => $recyclingLocation->name,
            ])->values(),
            'location' => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'name' => $location->name,
                'address' => $location->address,
                'city' => $location->city,
                'state' => $location->state,
                'zip' => $location->zip,
                'country' => $location->country,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'type' => $location->type,
                'is_active' => $location->is_active,
                'recycling_location_id' => $location->recyclingLocation?->guid,
                'emails' => $location->emails,
                'expected_arrival_time' => $this->formatExpectedArrivalTime($location->expected_arrival_time),
            ],
        ]);
    }

    /**
     * Update the specified location in storage.
     */
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'short_code' => [
                'required',
                'string',
                'max:50',
            ],
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:distribution_center,recycling,pickup',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean',
            'recycling_location_id' => 'nullable|uuid|exists:locations,guid',
            'emails' => 'nullable|string', // comma-separated list
            'expected_arrival_time' => 'nullable|date_format:H:i',
        ]);

        $oldRecyclingId = $location->recycling_location_id;
        $addressFields = ['address', 'city', 'state', 'zip', 'country'];
        $addressChanged = $location->wasChanged($addressFields);

        // If changing type away from distribution_center (or to non-DC), clear recycling link
        if (
            ($location->type === 'distribution_center' && $validated['type'] !== 'distribution_center') ||
            ($location->type !== 'distribution_center' && $validated['type'] !== 'distribution_center')
        ) {
            $validated['recycling_location_id'] = null;
        }

        // Parse comma-separated emails into array
        $emails = $validated['emails']
            ? array_filter(array_map('trim', explode(',', $validated['emails'])))
            : [];
        $validated['emails'] = $emails;
        $validated['expected_arrival_time'] = $this->normalizeExpectedArrivalTime(
            $validated['expected_arrival_time'] ?? null
        );
        $validated['recycling_location_id'] = $this->resolveLocationRecordIdFromGuid($validated['recycling_location_id'] ?? null);

        // Update the location
        $location->update($validated);

        // Recalculate distances if relevant fields changed
        if ($location->type === 'distribution_center') {
            $recyclingChanged = $location->wasChanged('recycling_location_id')
                            || $oldRecyclingId !== $location->recycling_location_id;

            if ($recyclingChanged || $addressChanged) {
                $this->recalculateDistancesForDc($location);
            }
        } elseif ($location->type === 'recycling') {
            if ($addressChanged) {
                $this->recalculateDistancesForRecycling($location);
            }
        }

        return redirect()->route('admin.locations.show', $location)
            ->with('success', 'Location updated successfully.');
    }

    private function normalizeExpectedArrivalTime(?string $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        return Carbon::createFromFormat('H:i', $time)
            ->setDate(1970, 1, 1)
            ->format('Y-m-d H:i:s');
    }

    private function formatExpectedArrivalTime($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->format('H:i');
    }

    /**
     * Remove the specified location from storage.
     */
    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location deleted successfully.');
    }

    /**
     * Display distances from DCs to their recycling locations.
     */
    public function recyclingDistances(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $recyclingId = $request->input('recycling_id');

        $query = Location::where('type', 'distribution_center')
            ->with('recyclingLocation:id,short_code,address');

        if ($recyclingId === 'none') {
            $query->whereNull('recycling_location_id');
        } elseif ($recyclingId && is_string($recyclingId)) {
            $query->where('recycling_location_id', $this->resolveLocationRecordIdFromGuid($recyclingId));
        }

        $dcLocations = $query->paginate($perPage);

        $distances = $dcLocations->through(function ($dc) {
            $rec = $dc->recyclingLocation;

            if (! $rec) {
                return [
                    'dc_id' => $dc->guid,
                    'dc_short_code' => $dc->short_code,
                    'rec_id' => null,
                    'rec_short_code' => null,
                    'distance_km' => null,
                    'distance_miles' => null,
                    'duration_text' => 'No recycling assigned',
                    'route_coords' => [],
                ];
            }

            $distance = $dc->distanceTo($rec);

            return [
                'dc_id' => $dc->guid,
                'dc_short_code' => $dc->short_code,
                'rec_id' => $rec->guid,
                'rec_short_code' => $rec->short_code,
                'distance_km' => $distance['km'] ?? null,
                'distance_miles' => $distance['miles'] ?? null,
                'duration_text' => $distance['duration_text'] ?? '—',
                'route_coords' => $distance['route_coords'] ?? [],
            ];
        });

        $recyclingLocations = Location::where('type', 'recycling')
            ->orderBy('short_code')
            ->get(['id', 'guid', 'short_code']);

        return Inertia::render('Admin/Locations/RecyclingDistance', [
            'distances' => $distances,
            'recycling_locations' => $recyclingLocations->map(fn (Location $recyclingLocation) => [
                'id' => $recyclingLocation->guid,
                'short_code' => $recyclingLocation->short_code,
            ])->values(),
        ]);
    }

    public function recyclingDistanceMap($dc_id, $rec_id)
    {
        $dc = $this->findLocationByGuidOrFail((string) $dc_id);
        $rec = $this->findLocationByGuidOrFail((string) $rec_id);

        // Build ordered array: DC first, Recycling second
        $locationIds = [$dc->guid, $rec->guid];

        $locations = Location::orderBy('short_code')
            ->get(['id', 'guid', 'short_code', 'address', 'type']);

        return Inertia::render('Admin/Locations/MultiLocationRoute', [
            'locations' => $locations->map(fn (Location $location) => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'address' => $location->address,
                'type' => $location->type,
            ])->values(),
            'preselected' => implode(',', $locationIds),
            'mapbox_token' => config('services.mapbox.key'),
        ]);
    }

    /**
     * Show the multi-location route planner page.
     */
    public function multiRoute()
    {
        $locations = Location::orderBy('short_code')
            ->get(['id', 'guid', 'short_code', 'address', 'type']);

        return Inertia::render('Admin/Locations/MultiLocationRoute', [
            'locations' => $locations->map(fn (Location $location) => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'address' => $location->address,
                'type' => $location->type,
            ])->values(),
            'mapbox_token' => config('services.mapbox.key'),
        ]);
    }

    /**
     * Calculate multi-location route using all waypoints at once (open path, no loop).
     */
    public function calculateMultiRoute(Request $request)
    {
        $validated = $request->validate([
            'location_ids' => 'required|array|min:2',
            'location_ids.*' => 'uuid|exists:locations,guid',
        ]);

        $locationIds = $validated['location_ids'];

        // Preserve the exact order from the frontend (no sorting)
        $cacheKey = 'mapbox_multi_route:'.md5(implode('|', $locationIds));

        $routeData = Cache::remember($cacheKey, now()->addDays(7), function () use ($locationIds) {
            // Load locations in the requested order
            $locations = Location::whereIn('guid', $locationIds)
                ->select('id', 'guid', 'short_code', 'address', 'latitude', 'longitude')
                ->get()
                ->keyBy('guid');

            // Build waypoints array with exact coordinates
            $waypoints = [];
            foreach ($locationIds as $id) {
                $loc = $locations[$id] ?? null;
                if (! $loc) {
                    return ['error' => 'Missing location data'];
                }

                if (! $loc->latitude || ! $loc->longitude) {
                    return ['error' => "Location {$loc->short_code} has no coordinates"];
                }

                $waypoints[] = [$loc->longitude, $loc->latitude]; // Mapbox: [lng, lat]
            }

            // If only 2 points, we can still use directions API
            if (count($waypoints) < 2) {
                return ['error' => 'Not enough locations for a route'];
            }

            // Build Mapbox Directions URL with all waypoints
            $coordString = implode(';', array_map(fn ($c) => implode(',', $c), $waypoints));

            $token = config('services.mapbox.key');
            if (! $token) {
                return ['error' => 'Mapbox token not configured'];
            }

            $response = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/{$coordString}", [
                'access_token' => $token,
                'geometries' => 'geojson',
                'overview' => 'full',
                'steps' => 'false',
            ]);

            if (! $response->successful() || empty($response['routes'])) {
                return ['error' => 'Failed to calculate multi-point route'];
            }

            $route = $response['routes'][0];

            $meters = $route['distance'];
            $seconds = $route['duration'];

            return [
                'total_km' => round($meters / 1000, 1),
                'total_miles' => round(($meters / 1000) * 0.621371, 1),
                'total_duration' => $this->secondsToHumanTime($seconds),
                'route_coords' => $route['geometry']['coordinates'] ?? [],
                'waypoints' => $waypoints,  // exact [lng, lat] for each stop
            ];
        });

        // Load all locations for the dropdown
        $allLocations = Location::orderBy('short_code')
            ->get(['id', 'guid', 'short_code', 'address', 'type']);

        return Inertia::render('Admin/Locations/MultiLocationRoute', [
            'locations' => $allLocations->map(fn (Location $location) => [
                'id' => $location->guid,
                'short_code' => $location->short_code,
                'address' => $location->address,
                'type' => $location->type,
            ])->values(),
            'route_data' => $routeData,
            'mapbox_token' => config('services.mapbox.key'),
            'default_rate_per_mile' => 2.50,
        ]);
    }

    /**
     * Core logic to compute multi-route (extracted so it can be called without closure issues).
     */
    private function computeMultiRoute(array $locationIds): array
    {
        $locations = Location::whereIn('id', $locationIds)
            ->select('id', 'short_code', 'address', 'latitude', 'longitude')
            ->get()
            ->keyBy('id');

        $allRouteCoords = [];
        $totalKm = 0.0;
        $totalSeconds = 0;

        for ($i = 0; $i < count($locationIds) - 1; $i++) {
            $fromId = $locationIds[$i];
            $toId = $locationIds[$i + 1];

            $from = $locations[$fromId] ?? null;
            $to = $locations[$toId] ?? null;

            if (! $from || ! $to) {
                return ['error' => 'Missing location data'];
            }

            $distance = $from->distanceTo($to);

            if (isset($distance['error'])) {
                return $distance;
            }

            $totalKm += $distance['km'] ?? 0;
            $totalSeconds += ($distance['duration_minutes'] ?? 0) * 60;

            $segmentCoords = $distance['route_coords'] ?? [];
            if ($i > 0 && ! empty($segmentCoords)) {
                array_shift($segmentCoords); // remove duplicate connecting point
            }

            $allRouteCoords = array_merge($allRouteCoords, $segmentCoords);
        }

        // Safety: Ensure no accidental closure
        if (count($allRouteCoords) > 2) {
            $first = $allRouteCoords[0];
            $last = end($allRouteCoords);
            if ($first[0] === $last[0] && $first[1] === $last[1]) {
                array_pop($allRouteCoords);
            }
        }

        return [
            'total_km' => round($totalKm, 1),
            'total_miles' => round($totalKm * 0.621371, 1),
            'total_duration' => $this->secondsToHumanTime($totalSeconds),
            'route_coords' => $allRouteCoords,
        ];
    }

    /**
     * Helper to convert seconds to human-readable time.
     */
    private function secondsToHumanTime(int $seconds): string
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

    /**
     * Recalculate distances for a DC when its recycling or address changes.
     */
    private function recalculateDistancesForDc(Location $dc)
    {
        $rec = $dc->recyclingLocation;

        if (! $rec) {
            LocationDistance::where('dc_id', $dc->id)->delete();

            return;
        }

        $dc->distanceTo($rec, true); // force recalculation
    }

    /**
     * Recalculate distances for all DCs linked to a changed recycling location.
     */
    private function recalculateDistancesForRecycling(Location $recycling)
    {
        $linkedDcs = Location::where('type', 'distribution_center')
            ->where('recycling_location_id', $recycling->id)
            ->get();

        foreach ($linkedDcs as $dc) {
            $dc->distanceTo($recycling, true);
        }
    }

    public function export()
    {
        $locations = Location::all();

        $callback = function () use ($locations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'Short Code',
                'Name',
                'Address',
                'City',
                'State',
                'ZIP',
                'Country',
                'Latitude',
                'Longitude',
                'Type',
                'Created At',
            ]);

            foreach ($locations as $loc) {
                fputcsv($file, [
                    $loc->id,
                    $loc->guid,
                    $loc->short_code,
                    $loc->name ?? '',
                    $loc->address ?? '',
                    $loc->city ?? '',
                    $loc->state ?? '',
                    $loc->zip ?? '',
                    $loc->country ?? '',
                    $loc->latitude ?? '',
                    $loc->longitude ?? '',
                    $loc->type ?? '',
                    $loc->created_at,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="locations-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $handle = fopen($request->file('file')->getPathname(), 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(trim($row[1] ?? ''))) {
                continue;
            } // skip if short_code empty

            Location::updateOrCreate(
                ['short_code' => trim($row[1])],
                [
                    'name' => trim($row[2] ?? null),
                    'address' => trim($row[3] ?? null),
                    'city' => trim($row[4] ?? null),
                    'state' => trim($row[5] ?? null),
                    'zip' => trim($row[6] ?? null),
                    'country' => trim($row[7] ?? null),
                    'latitude' => is_numeric($row[8]) ? (float) $row[8] : null,
                    'longitude' => is_numeric($row[9]) ? (float) $row[9] : null,
                    'type' => trim($row[10] ?? null),
                ]
            );
        }

        fclose($handle);

        return back()->with('success', 'Locations imported successfully!');
    }

    private function resolveLocationRecordIdFromGuid(?string $guid): string|int|null
    {
        if (blank($guid)) {
            return null;
        }

        return Location::query()->where('guid', $guid)->value('id');
    }

    private function findLocationByGuidOrFail(string $guid): Location
    {
        return Location::query()->where('guid', $guid)->firstOrFail();
    }
}
