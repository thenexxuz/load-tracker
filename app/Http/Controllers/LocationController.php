<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\LocationDistance;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    /**
     * Display a listing of locations.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:25',
            'search'   => 'nullable|string|max:500',
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

        $locations = $query->paginate($validated['per_page'] ?? 15 );

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations,
        ]);
    }

    /**
     * Show the form for creating a new location.
     */
    public function create()
    {
        return Inertia::render('Admin/Locations/Create');
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'short_code' => 'required|string|max:50|unique:locations',
            'name'       => 'nullable|string|max:255',
            'type'       => 'required|in:distribution_center,recycling,other',
            'address'    => 'nullable|string|max:255',
            'city'       => 'nullable|string|max:255',
            'state'      => 'nullable|string|max:255',
            'zip'        => 'nullable|string|max:20',
            'country'    => 'nullable|string|max:255',
            'latitude'   => 'nullable|numeric|between:-90,90',
            'longitude'  => 'nullable|numeric|between:-180,180',
            // Add other validation rules as needed
        ]);

        Location::create($validated);

        return redirect()->route('admin.locations.index')
                         ->with('success', 'Location created successfully.');
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location)
    {
        $location->load('recyclingLocation');

        return Inertia::render('Admin/Locations/Show', [
            'location' => $location,
        ]);
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Location $location)
    {
        return Inertia::render('Admin/Locations/Edit', [
            'location' => $location,
        ]);
    }

    /**
     * Update the specified location in storage.
     */
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'short_code' => 'required|string|max:50|unique:locations,short_code,' . $location->id,
            'name'       => 'nullable|string|max:255',
            'type'       => 'required|in:distribution_center,recycling,other',
            'address'    => 'nullable|string|max:255',
            'city'       => 'nullable|string|max:255',
            'state'      => 'nullable|string|max:255',
            'zip'        => 'nullable|string|max:20',
            'country'    => 'nullable|string|max:255',
            'latitude'   => 'nullable|numeric|between:-90,90',
            'longitude'  => 'nullable|numeric|between:-180,180',
            'recycling_location_id' => 'nullable|exists:locations,id|prohibited_if:type,recycling',
        ]);

        $oldRecyclingId = $location->recycling_location_id;
        $addressFields = ['address', 'city', 'state', 'zip', 'country'];
        $addressChanged = $location->wasChanged($addressFields);

        $location->update($validated);

        // Recalculate distances if relevant fields changed
        if ($location->type === 'distribution_center') {
            $recyclingChanged = $location->wasChanged('recycling_location_id') || $oldRecyclingId !== $location->recycling_location_id;

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
        } elseif ($recyclingId && is_numeric($recyclingId)) {
            $query->where('recycling_location_id', $recyclingId);
        }

        $dcLocations = $query->paginate($perPage);

        $distances = $dcLocations->through(function ($dc) {
            $rec = $dc->recyclingLocation;

            if (!$rec) {
                return [
                    'dc_id'           => $dc->id,
                    'dc_short_code'   => $dc->short_code,
                    'rec_id'          => null,
                    'rec_short_code'  => null,
                    'distance_km'     => null,
                    'distance_miles'  => null,
                    'duration_text'   => 'No recycling assigned',
                    'route_coords'    => [],
                ];
            }

            $distance = $dc->distanceTo($rec);

            return [
                'dc_id'           => $dc->id,
                'dc_short_code'   => $dc->short_code,
                'rec_id'          => $rec->id,
                'rec_short_code'  => $rec->short_code,
                'distance_km'     => $distance['km'] ?? null,
                'distance_miles'  => $distance['miles'] ?? null,
                'duration_text'   => $distance['duration_text'] ?? '—',
                'route_coords'    => $distance['route_coords'] ?? [],
            ];
        });

        $recyclingLocations = Location::where('type', 'recycling')
            ->select('id', 'short_code')
            ->orderBy('short_code')
            ->get();

        return Inertia::render('Admin/Locations/RecyclingDistance', [
            'distances'           => $distances,
            'recycling_locations' => $recyclingLocations,
        ]);
    }

    /**
     * Show the multi-location route planner page.
     */
    public function multiRoute()
    {
        $locations = Location::select('id', 'short_code', 'address', 'type')
            ->orderBy('short_code')
            ->get();

        return Inertia::render('Admin/Locations/MultiLocationRoute', [
            'locations'    => $locations,
            'mapbox_token' => config('services.mapbox.key'),
        ]);
    }

    /**
     * Calculate multi-location route (called via POST from frontend).
     */
    public function calculateMultiRoute(Request $request)
    {
        $validated = $request->validate([
            'location_ids' => 'required|array|min:2',
            'location_ids.*' => 'exists:locations,id',
        ]);

        $locationIds = $validated['location_ids'];

        // Sort for consistent caching (order matters for route)
        sort($locationIds);
        $cacheKey = 'mapbox_multi_route:' . md5(implode('|', $locationIds));

        $routeData = \Cache::remember($cacheKey, now()->addDays(7), function () use ($locationIds) {
            $locations = Location::whereIn('id', $locationIds)
                ->select('id', 'short_code', 'address', 'latitude', 'longitude')
                ->get()
                ->keyBy('id');

            $allRouteCoords = [];
            $totalKm = 0.0;
            $totalSeconds = 0;

            for ($i = 0; $i < count($locationIds) - 1; $i++) {
                $fromId = $locationIds[$i];
                $toId   = $locationIds[$i + 1];

                $from = $locations[$fromId];
                $to   = $locations[$toId];

                if (!$from || !$to) {
                    return ['error' => 'Missing location data'];
                }

                $distance = $from->distanceTo($to);

                if (isset($distance['error'])) {
                    return $distance;
                }

                $totalKm += $distance['km'] ?? 0;
                $totalSeconds += ($distance['duration_minutes'] ?? 0) * 60;

                $segmentCoords = $distance['route_coords'] ?? [];
                if ($i > 0 && !empty($segmentCoords)) {
                    array_shift($segmentCoords); // remove duplicate connecting point
                }

                $allRouteCoords = array_merge($allRouteCoords, $segmentCoords);
            }

            return Inertia::render('Admin/Locations/MultiLocationRoute', [
                'locations'    => $locations,
                'mapbox_token' => config('services.mapbox.key'),
                'route_data'   => [
                    'total_km' => round($totalKm, 1),
                    'total_miles' => round($totalKm * 0.621371, 1),
                    'total_duration' => $this->secondsToHumanTime($totalSeconds),
                    'route_coords' => $allRouteCoords,
                ],
            ]);
        });

        return response()->json($routeData);
    }

    /**
     * Helper to convert seconds to human-readable time.
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

    /**
     * Recalculate distances for a DC when its recycling or address changes.
     */
    private function recalculateDistancesForDc(Location $dc)
    {
        $rec = $dc->recyclingLocation;

        if (!$rec) {
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
}
