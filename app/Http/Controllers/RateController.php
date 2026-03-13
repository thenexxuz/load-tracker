<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class RateController extends Controller
{
    public function index(Request $request)
    {
        $query = Rate::query()
            ->with([
                'pickupLocation:id,short_code,name',
                'dcLocation:id,short_code,name',
                'carrier:id,name,short_code',
            ])
            ->orderBy('effective_from', 'desc')
            ->orderBy('effective_to', 'desc')
            ->orderBy('type');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $rates = $query->paginate(15);

        return Inertia::render('Admin/Rates/Index', [
            'rates' => $rates,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Rates/Create', [
            'locations' => Location::select('id', 'short_code', 'name', 'city', 'state')
                ->orderBy('short_code')
                ->get(),
            'carriers' => Carrier::select('id', 'name', 'short_code')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'type'                => ['required', Rule::in(['flat', 'per_mile'])],
            'rate'                => 'required|numeric|min:0.01',
            'pickup_location_id'  => 'nullable|exists:locations,id',
            'dc_location_id'      => 'nullable|exists:locations,id',
            'carrier_id'          => 'nullable|exists:carriers,id',
            'effective_from'      => 'nullable|date',
            'effective_to'        => 'nullable|date|after_or_equal:effective_from',
        ]);

        Rate::create($validated);

        return redirect()
            ->route('admin.rates.index')
            ->with('success', 'Rate created successfully.');
    }

    public function show(Rate $rate)
    {
        $rate->load([
            'carrier',
            'pickupLocation',
            'dcLocation',
            'notes.user',
        ]);

        return Inertia::render('Admin/Rates/Show', ['rate' => $rate]);
    }

    public function edit(Rate $rate)
    {
        $rate->load([
            'pickupLocation:id,short_code,name,city,state',
            'dcLocation:id,short_code,name,city,state',
            'carrier:id,name,short_code',
        ]);

        return Inertia::render('Admin/Rates/Edit', [
            'rate' => $rate,
            'locations' => Location::select('id', 'short_code', 'name', 'city', 'state')
                ->orderBy('short_code')
                ->get(),
            'carriers' => Carrier::select('id', 'name', 'short_code')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Rate $rate)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'type'                => ['required', Rule::in(['flat', 'per_mile'])],
            'rate'                => 'required|numeric|min:0.01',
            'pickup_location_id'  => 'nullable|exists:locations,id',
            'dc_location_id'      => 'nullable|exists:locations,id',
            'carrier_id'          => 'nullable|exists:carriers,id',
            'effective_from'      => 'nullable|date',
            'effective_to'        => 'nullable|date|after_or_equal:effective_from',
        ]);

        $rate->update($validated);

        return redirect()->route('admin.rates.show', $rate->id)
            ->with('success', 'Rate updated successfully.');
    }

    public function destroy(Rate $rate)
    {
        $rate->delete();

        return redirect()->route('admin.rates.index')->with('success', 'Rate deleted successfully.');
    }
}
