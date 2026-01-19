<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CarrierController extends Controller
{
    public function index()
    {
        $carriers = Carrier::query()
            ->when(request('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Admin/Carriers/Index', [
            'carriers' => $carriers,
            'filters' => request()->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Carriers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'short_code' => 'required|string|max:20|unique:carriers,short_code',
            'wt_code' => 'nullable|string|max:50|unique:carriers,wt_code',
            'name' => 'required|string|max:255',
            'emails' => 'nullable|string', // comma separated
            'is_active' => 'boolean',
        ]);

        Carrier::create($validated);

        return redirect()->route('admin.carriers.index')
            ->with('success', 'Carrier created successfully.');
    }

    public function show(Carrier $carrier)
    {
        return Inertia::render('Admin/Carriers/Show', [
            'carrier' => $carrier,
        ]);
    }

    public function edit(Carrier $carrier)
    {
        return Inertia::render('Admin/Carriers/Edit', [
            'carrier' => $carrier,
        ]);
    }

    public function update(Request $request, Carrier $carrier)
    {
        $validated = $request->validate([
            'short_code' => 'required|string|max:20|unique:carriers,short_code,'.$carrier->id,
            'wt_code' => 'nullable|string|max:50|unique:carriers,wt_code,'.$carrier->id,
            'name' => 'required|string|max:255',
            'emails' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $carrier->update($validated);

        return redirect()->route('admin.carriers.index')
            ->with('success', 'Carrier updated successfully.');
    }

    public function destroy(Carrier $carrier)
    {
        // Optional: Add check if carrier is in use by loads
        // if ($carrier->loads()->exists()) {
        //     return back()->withErrors(['error' => 'Cannot delete carrier in use.']);
        // }

        $carrier->delete();

        return redirect()->route('admin.carriers.index')
            ->with('success', 'Carrier deleted successfully.');
    }
}
