<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RateController extends Controller
{
    public function index()
    {
        $rates = Rate::with(['carrier', 'pickupLocation', 'dcLocation'])->paginate(10);

        return Inertia::render('Admin/Rates/Index', ['rates' => $rates]);
    }

    public function create()
    {
        $carriers = Carrier::select('id', 'name', 'short_code')->get();
        $pickupLocations = Location::where('type', 'pickup')->select('id', 'short_code', 'name', 'city', 'state')->get();
        $dcLocations = Location::where('type', 'distribution_center')->select('id', 'short_code', 'name', 'city', 'state')->get();

        return Inertia::render('Admin/Rates/Create', [
            'carriers' => $carriers,
            'pickupLocations' => $pickupLocations,
            'dcLocations' => $dcLocations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'carrier_id' => 'required|exists:carriers,id',
            'pickup_location_id' => 'required|exists:locations,id',
            'dc_location_id' => 'required|exists:locations,id',
            'rate' => 'required|numeric|min:0',
        ]);

        Rate::create($validated);

        return redirect()->route('admin.rates.index')->with('success', 'Rate created successfully.');
    }

    public function show(Rate $rate)
    {
        $rate->load(['carrier', 'pickupLocation', 'dcLocation']);

        return Inertia::render('Admin/Rates/Show', ['rate' => $rate]);
    }

    public function edit(Rate $rate)
    {
        $rate->load(['carrier', 'pickupLocation', 'dcLocation']);

        $carriers = Carrier::select('id', 'name', 'short_code')->get();
        $pickupLocations = Location::where('type', 'pickup')->select('id', 'short_code', 'name')->get();
        $dcLocations = Location::where('type', 'distribution_center')->select('id', 'short_code', 'name')->get();

        return Inertia::render('Admin/Rates/Edit', [
            'rate' => $rate,
            'carriers' => $carriers,
            'pickupLocations' => $pickupLocations,
            'dcLocations' => $dcLocations,
        ]);
    }

    public function update(Request $request, Rate $rate)
    {
        $validated = $request->validate([
            'carrier_id' => 'required|exists:carriers,id',
            'pickup_location_id' => 'required|exists:locations,id',
            'dc_location_id' => 'required|exists:locations,id',
            'rate' => 'required|numeric|min:0',
        ]);

        $rate->update($validated);

        return redirect()->route('admin.rates.index')->with('success', 'Rate updated successfully.');
    }

    public function destroy(Rate $rate)
    {
        $rate->delete();

        return redirect()->route('admin.rates.index')->with('success', 'Rate deleted successfully.');
    }
}
