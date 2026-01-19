<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::query()
            ->with('recyclingLocation') // Load the relationship
            ->when(request('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%")->orWhere('short_code', 'like', "%{$search}%"))
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

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location deleted successfully.');
    }
}
