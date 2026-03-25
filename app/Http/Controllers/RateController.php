<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RateController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = $request->input('sort_by');
        $sortDirection = $request->input('sort_direction') === 'desc' ? 'desc' : 'asc';
        $status = $request->input('status');
        $type = $request->input('type');
        $carrierId = $request->input('carrier_id');

        $query = Rate::query()
            ->with([
                'pickupLocation:id,short_code,name',
                'carrier:id,name,short_code',
            ]);

        if ($request->search) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        if (in_array($type, ['flat', 'per_mile'], true)) {
            $query->where('type', $type);
        }

        if ($carrierId !== null && $carrierId !== '') {
            $query->where('carrier_id', $carrierId);
        }

        if ($status === 'active') {
            $query->active();
        }

        if ($status === 'inactive') {
            $query->where(function ($inactiveQuery) {
                $inactiveQuery
                    ->where('effective_from', '>', now())
                    ->orWhere('effective_to', '<', now());
            });
        }

        if ($sortBy === 'name') {
            $query
                ->orderByRaw('case when name is null then 1 else 0 end')
                ->orderBy('name', $sortDirection);
        } else {
            $sortBy = null;
        }

        $query
            ->orderBy('effective_from', 'desc')
            ->orderBy('effective_to', 'desc')
            ->orderBy('type');

        $rates = $query
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Rates/Index', [
            'rates' => $rates,
            'carriers' => Carrier::query()
                ->select('id', 'name', 'short_code')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $request->input('search'),
                'type' => $type,
                'carrier_id' => $carrierId,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_direction' => $sortBy === 'name' ? $sortDirection : null,
            ],
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
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(['flat', 'per_mile'])],
            'rate' => 'required|numeric|min:0.01',
            'pickup_location_id' => 'nullable|exists:locations,id',
            'destination_city' => 'nullable|string|max:255',
            'destination_state' => 'nullable|string|max:2',
            'destination_country' => 'nullable|string|max:2',
            'carrier_id' => 'nullable|exists:carriers,id',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
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
            'notes.user',
        ]);

        return Inertia::render('Admin/Rates/Show', ['rate' => $rate]);
    }

    public function edit(Rate $rate)
    {
        $rate->load([
            'pickupLocation:id,short_code,name,city,state',
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
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(['flat', 'per_mile'])],
            'rate' => 'required|numeric|min:0.01',
            'pickup_location_id' => 'nullable|exists:locations,id',
            'destination_city' => 'nullable|string|max:255',
            'destination_state' => 'nullable|string|max:2',
            'destination_country' => 'nullable|string|max:2',
            'carrier_id' => 'nullable|exists:carriers,id',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $rate->update($validated);

        return redirect()->route('admin.rates.show', $rate->id)
            ->with('success', 'Rate updated successfully.');
    }

    public function destroy(Request $request, Rate $rate)
    {
        $rate->delete();

        return redirect()
            ->route('admin.rates.index', $request->only([
                'search',
                'type',
                'carrier_id',
                'status',
                'sort_by',
                'sort_direction',
                'per_page',
                'page',
            ]))
            ->with('success', 'Rate deleted successfully.');
    }
}
