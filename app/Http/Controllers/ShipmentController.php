<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShipmentController extends Controller
{
    public function index()
    {
        $shipments = Shipment::query()
            ->with(['shipperLocation:id,short_code,name', 'dcLocation:id,short_code,name', 'carrier:id,name'])
            ->when(request('search'), function ($q, $search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('bol', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%")
                    ->orWhereHas('shipperLocation', fn ($q) => $q->where('short_code', 'like', "%{$search}%"))
                    ->orWhereHas('dcLocation', fn ($q) => $q->where('short_code', 'like', "%{$search}%"));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Shipments/Index', [
            'shipments' => $shipments,
            'filters' => request()->only('search'),
        ]);
    }

    public function create()
    {
        $pickupLocations = Location::where('type', 'pickup')
            ->select('id', 'short_code', 'name')
            ->get();

        $dcLocations = Location::where('type', 'distribution_center')
            ->select('id', 'short_code', 'name')
            ->get();

        $carriers = Carrier::select('id', 'name', 'short_code')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Admin/Shipments/Create', [
            'pickupLocations' => $pickupLocations,
            'dcLocations' => $dcLocations,
            'carriers' => $carriers,
        ]);
    }

    public function store(Request $request)
    {
        $messages = [
            'pickup_date.after_or_equal' => 'The pickup date must be on or after the drop date.',
            'delivery_date.after_or_equal' => 'The delivery date must be on or after the pickup date.',
        ];

        $validated = $request->validate([
            'shipment_number' => ['required', 'string', 'max:50', 'unique:shipments'],
            'bol' => ['nullable', 'string', 'max:100'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:pending,picked_up,in_transit,delivered,cancelled'],
            'shipper_location_id' => ['required', 'exists:locations,id'],
            'dc_location_id' => ['nullable', 'exists:locations,id'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'drop_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:drop_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'rack_qty' => ['required', 'integer', 'min:0'],
            'load_bar_qty' => ['required', 'integer', 'min:0'],
            'strap_qty' => ['required', 'integer', 'min:0'],
            'trailer' => ['nullable', 'string', 'max:50'],
            'drayage' => ['boolean'],
            'on_site' => ['boolean'],
            'shipped' => ['boolean'],
            'recycling_sent' => ['boolean'],
            'paperwork_sent' => ['boolean'],
            'delivery_alert_sent' => ['boolean'],
        ], $messages);

        Shipment::create($validated);

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment created successfully.');
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['shipperLocation:id,short_code,name', 'dcLocation:id,short_code,name', 'carrier:id,name']);

        return Inertia::render('Admin/Shipments/Show', [
            'shipment' => $shipment,
        ]);
    }

    public function edit(Shipment $shipment)
    {
        $shipment->load(['shipperLocation', 'dcLocation', 'carrier']);

        $pickupLocations = Location::where('type', 'pickup')
            ->select('id', 'short_code', 'name')
            ->get();

        $dcLocations = Location::where('type', 'distribution_center')
            ->select('id', 'short_code', 'name')
            ->get();

        $carriers = Carrier::select('id', 'name', 'short_code')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Admin/Shipments/Edit', [
            'shipment' => $shipment,
            'pickupLocations' => $pickupLocations,
            'dcLocations' => $dcLocations,
            'carriers' => $carriers,
        ]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        $messages = [
            'pickup_date.after_or_equal' => 'The pickup date must be on or after the drop date.',
            'delivery_date.after_or_equal' => 'The delivery date must be on or after the pickup date.',
        ];

        $validated = $request->validate([
            'shipment_number' => 'required|string|max:50|unique:shipments,shipment_number,'.$shipment->id,
            'bol' => 'nullable|string|max:100',
            'po_number' => 'nullable|string|max:100',
            'status' => 'required|string|in:pending,picked_up,in_transit,delivered,cancelled',
            'shipper_location_id' => 'required|exists:locations,id',
            'dc_location_id' => 'nullable|exists:locations,id',
            'carrier_id' => 'nullable|exists:carriers,id',
            'drop_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:drop_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'rack_qty' => 'required|integer|min:0',
            'load_bar_qty' => 'required|integer|min:0',
            'strap_qty' => 'required|integer|min:0',
            'trailer' => 'nullable|string|max:50',
            'drayage' => 'boolean',
            'on_site' => 'boolean',
            'shipped' => 'boolean',
            'recycling_sent' => 'boolean',
            'paperwork_sent' => 'boolean',
            'delivery_alert_sent' => 'boolean',
        ], $messages);

        $shipment->update($validated);

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment updated successfully.');
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment deleted successfully.');
    }
}
