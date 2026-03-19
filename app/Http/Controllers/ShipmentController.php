<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use App\Models\Shipment;
use App\Models\Template;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use League\Csv\Writer;
use Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        // Log incoming data for debugging
        \Log::info('Shipments index', [
            'method' => $request->method(),
            'payload' => $request->all(),
        ]);

        // Use $request->input() or $request->all() - NOT $request->query()
        $perPage = $request->input('per_page', 15);
        $search = trim($request->input('search') ?? '');

        $query = Shipment::query()->withCount('notes')
            ->with(['pickupLocation', 'dcLocation', 'carrier'])
            ->latest();

        // Apply filters from payload
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('bol', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%");
            });
        }

        $excludedStatuses = $request->input('excluded_statuses', []);
        if ($excludedStatuses) {
            $query->whereNotIn('status', $excludedStatuses);
        }

        // Exclude shippers (pickup locations)
        if ($excludedShippers = $request->input('excluded_pickup_locations')) {
            $query->whereHas('pickupLocation', function ($q) use ($excludedShippers) {
                $q->whereNotIn('short_code', (array) $excludedShippers);
            });
        }

        // Exclude DCs
        if ($excludedDcs = $request->input('excluded_dc_locations')) {
            $query->whereHas('dcLocation', function ($q) use ($excludedDcs) {
                $q->whereNotIn('short_code', (array) $excludedDcs);
            });
        }

        // Exclude carriers
        $excludedCarriers = $request->input('excluded_carriers', []);

        // Special case: if user excluded ALL carriers → show only shipments with null carrier
        $allCarrierNames = Carrier::pluck('name')->unique()->toArray();
        if (is_array($excludedCarriers) && count(array_unique($excludedCarriers)) === count($allCarrierNames)) {
            $query->whereNull('carrier_id');
        }
        // Normal case: exclude the selected carriers
        elseif (!empty($excludedCarriers)) {
            $query->whereNotIn('carrier_id', function ($sub) use ($excludedCarriers) {
                $sub->select('id')
                    ->from('carriers')
                    ->whereIn('name', $excludedCarriers);
            });
        }

        // Drop Date filter
        $dropStart = $request->input('drop_start');
        $dropEnd   = $request->input('drop_end');

        if ($dropStart && $dropEnd) {
            $query->whereDate('drop_date', '>=', $dropStart)
                ->whereDate('drop_date', '<=', $dropEnd);
        } elseif ($dropStart) {
            $query->whereDate('drop_date', '>=', $dropStart);
        } elseif ($dropEnd) {
            $query->whereDate('drop_date', '<=', $dropEnd);
        }

        $shipments = $query->paginate($perPage);

        // Transform to add has_notes if you use that indicator
        $shipments->getCollection()->transform(function ($shipment) {
            $shipment->has_notes = $shipment->notes()->exists();
            return $shipment;
        });

        return Inertia::render('Admin/Shipments/Index', [
            'shipments' => $shipments,
            'statuses' => Shipment::distinct('status')->pluck('status')->sort()->values(),
            'all_shipper_codes' => Location::where('type', 'pickup')->pluck('short_code')->sort()->values(),
            'all_dc_codes' => Location::whereIn('type', ['distribution_center', 'pickup'])->pluck('short_code')->sort()->values(),
            'all_carrier_names' => Carrier::pluck('name')->sort()->values(),
            // Pass current filters back for frontend state restoration
            'filters' => $request->only([
                'search',
                'excluded_statuses',
                'excluded_pickup_locations',
                'excluded_dc_locations',
                'excluded_carriers',
                'drop_start',
                'drop_end',
                'per_page'
            ]),
        ]);
    }

    public function create()
    {
        $pickupLocations = Location::where('type', 'pickup')
            ->select('id', 'short_code', 'name')
            ->get();

        $dcLocations = Location::whereIn('type', ['distribution_center', 'pickup'])
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
            'shipment_number' => ['required', 'unique:shipments'],
            'bol' => ['nullable', 'string', 'max:100'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string'],
            'shipper_location_id' => ['required', 'exists:locations,id'],
            'dc_location_id' => ['nullable', 'exists:locations,id'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'drop_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:drop_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'rack_qty' => ['integer', 'min:0'],
            'load_bar_qty' => ['integer', 'min:0'],
            'strap_qty' => ['integer', 'min:0'],
            'trailer' => ['nullable', 'string', 'max:50'],
            'drayage' => ['nullable', 'string'],
            'on_site' => ['nullable', 'boolean'],
            'shipped' => ['nullable', 'boolean'],
            'recycling_sent' => ['nullable', 'boolean'],
            'paperwork_sent' => ['nullable', 'boolean'],
            'delivery_alert_sent' => ['nullable', 'boolean'],
        ], $messages);

        Shipment::create($validated);

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment created successfully.');
    }

    public function show(Shipment $shipment)
    {
        $shipment->load([
            'pickupLocation:id,short_code,name,address,city,state,country,zip,latitude,longitude',
            'dcLocation:id,short_code,name,address,city,state,country,zip,latitude,longitude,recycling_location_id',
            'dcLocation.recyclingLocation:id,short_code,name,latitude,longitude',
            'carrier:id,name,short_code',
            'notes.user',
        ]);

        // Build ordered waypoints: pickup → DC → (Recycling if exists)
        $waypoints = [];

        if ($shipment->pickupLocation && $shipment->pickupLocation->latitude && $shipment->pickupLocation->longitude) {
            $waypoints[] = [
                'id' => $shipment->pickupLocation->id,
                'short_code' => $shipment->pickupLocation->short_code,
                'name' => $shipment->pickupLocation->name,
                'type' => 'pickup',
                'lng' => $shipment->pickupLocation->longitude,
                'lat' => $shipment->pickupLocation->latitude,
            ];
        }

        if ($shipment->dcLocation && $shipment->dcLocation->latitude && $shipment->dcLocation->longitude) {
            $waypoints[] = [
                'id' => $shipment->dcLocation->id,
                'short_code' => $shipment->dcLocation->short_code,
                'name' => $shipment->dcLocation->name,
                'type' => 'dc',
                'lng' => $shipment->dcLocation->longitude,
                'lat' => $shipment->dcLocation->latitude,
            ];
        }

        // Add recycling if assigned to the DC
        if ($shipment->dcLocation?->recyclingLocation && $shipment->dcLocation->recyclingLocation->latitude && $shipment->dcLocation->recyclingLocation->longitude) {
            $waypoints[] = [
                'id' => $shipment->dcLocation->recyclingLocation->id,
                'short_code' => $shipment->dcLocation->recyclingLocation->short_code,
                'name' => $shipment->dcLocation->recyclingLocation->name,
                'type' => 'recycling',
                'lng' => $shipment->dcLocation->recyclingLocation->longitude,
                'lat' => $shipment->dcLocation->recyclingLocation->latitude,
            ];
        }

        $routeData = null;

        if (count($waypoints) >= 2) {
            // Build Mapbox Directions coordinates string: lng1,lat1;lng2,lat2;...
            $coordString = implode(';', array_map(fn($wp) => "{$wp['lng']},{$wp['lat']}", $waypoints));

            $token = config('services.mapbox.key');

            if ($token) {
                try {
                    $response = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/{$coordString}", [
                        'access_token' => $token,
                        'geometries' => 'geojson',
                        'overview' => 'full',
                    ]);

                    if (!empty($response['routes'])) {
                        $route = $response['routes'][0];

                        $totalMiles = round(($route['distance'] / 1000) * 0.621371, 1);

                        // Calculate segment distances
                        $pickupToDcMiles = $totalMiles;
                        $dcToRecyclingMiles = 0;

                        if (count($waypoints) === 3) {
                            // Approximate: pickup->DC is 2/3, DC->recycling is 1/3
                            $pickupToDcMiles = round($totalMiles * 0.67, 1);
                            $dcToRecyclingMiles = round($totalMiles * 0.33, 1);
                        }

                        $routeData = [
                            'route_coords' => $route['geometry']['coordinates'] ?? [],
                            'total_km' => round($route['distance'] / 1000, 1),
                            'total_miles' => $totalMiles,
                            'pickup_to_dc_miles' => $pickupToDcMiles,
                            'dc_to_recycling_miles' => $dcToRecyclingMiles,
                            'duration' => $this->secondsToHumanTime($route['duration']),
                            'waypoints' => $waypoints,
                        ];
                    } else {
                        throw new Exception();
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch route for shipment {$shipment->id}");
                }
            }
        }

        // Rates for the lane (pickup → destination)
        $dcLocation = $shipment->dcLocation;

        // Base query for regular rates
        $ratesQuery = Rate::query()
            ->where('pickup_location_id', $shipment->pickup_location_id)
            ->where(function ($query) use ($dcLocation) {
                $query->where(function ($q) use ($dcLocation) {
                    // Exact destination match
                    $q->where('destination_city', $dcLocation?->city)
                      ->where('destination_state', $dcLocation?->state)
                      ->where('destination_country', $dcLocation?->country);
                })->orWhere(function ($q) {
                    // No destination specified (fallback)
                    $q->whereNull('destination_city')
                      ->whereNull('destination_state')
                      ->whereNull('destination_country');
                });
            })
            ->with('carrier:id,name,short_code');

        // If carrier is assigned, show only rates for that carrier
        if ($shipment->carrier_id) {
            $ratesQuery->where('carrier_id', $shipment->carrier_id);
        }

        // Get regular rates
        $regularRates = $ratesQuery->orderBy('carrier_id')->orderBy('rate')->get();

        // Get recycling rates (separate query)
        $recyclingRates = Rate::query()
            ->where('name', 'Recycling')
            ->where('pickup_location_id', $shipment->dc_location_id)
            ->with('carrier:id,name,short_code')
            ->orderBy('carrier_id')
            ->orderBy('rate')
            ->get();

        // Combine rates, with regular rates first, then recycling rates
        $rates = $regularRates->merge($recyclingRates);

        // Transform rates to match frontend expectations
        $transformedRates = $rates->map(function ($rate) use ($shipment) {
            // Determine calculation type
            $calculationType = 'full_route'; // default

            if ($rate->name === 'Recycling') {
                $calculationType = 'dc_to_recycling';
            } elseif ($rate->destination_city === null && $rate->destination_state === null && $rate->destination_country === null) {
                $calculationType = 'pickup_to_dc';
            }

            return [
                'id' => $rate->id,
                'carrier' => $rate->carrier,
                'rate_per_mile' => $rate->rate,
                'effective_date' => $rate->effective_from?->format('Y-m-d'),
                'expires_at' => $rate->effective_to?->format('Y-m-d'),
                'notes' => $rate->notes,
                'type' => $rate->type,
                'name' => $rate->name,
                'calculation_type' => $calculationType,
            ];
        });

        return Inertia::render('Admin/Shipments/Show', [
            'shipment' => $shipment,
            'route_data' => $routeData,
            'mapbox_token' => config('services.mapbox.key'),
            'rates' => $transformedRates,
            'hasAssignedCarrier' => (bool) $shipment->carrier_id,
        ]);
    }

    // Optional helper (add to controller if not already present)
    private function secondsToHumanTime($seconds)
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return $h > 0 ? "{$h} hr {$m} min" : "{$m} min";
    }

    public function edit(Shipment $shipment)
    {
        $shipment->load(['pickupLocation', 'dcLocation', 'carrier']);

        $pickupLocations = Location::where('type', 'pickup')
            ->select('id', 'short_code', 'name')
            ->get();

        $dcLocations = Location::whereIn('type', ['distribution_center', 'pickup'])
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
            'shipment_number' => 'unique:shipments,shipment_number,'.$shipment->id,
            'bol' => 'nullable|string|max:100',
            'po_number' => 'nullable|string|max:100',
            'status' => 'required|string',
            'pickup_location_id' => 'required|exists:locations,id',
            'dc_location_id' => 'nullable|exists:locations,id',
            'carrier_id' => 'nullable|exists:carriers,id',
            'drop_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:drop_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'rack_qty' => 'integer|min:0',
            'load_bar_qty' => 'integer|min:0',
            'strap_qty' => 'integer|min:0',
            'trailer' => 'nullable|string|max:50',
            'drayage' => 'nullable|string',
            'on_site' => 'nullable|date',
            'shipped' => 'nullable|date',
            'recycling_sent' => 'nullable|date',
            'paperwork_sent' => 'nullable|date',
            'delivery_alert_sent' => 'nullable|date',
            'crossed' => 'nullable|date',
            'seal_number' => 'nullable|string|max:255',
            'drivers_id' => 'nullable|string|max:255',
        ], $messages);

        $shipment->update($validated);

        return redirect()->route('admin.shipments.show', $shipment->id)
            ->with('success', 'Shipment updated successfully.');
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return redirect()->route('admin.shipments.index')
            ->with('success', 'Shipment deleted successfully.');
    }

    /**
     * Import shipments from Power BI XLSX file
     * - Skips first two rows
     * - Uses third row as headers
     * - Failed rows stored in session → downloaded via separate route
     */
    public function pbiImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip first two rows
            $dataRows = array_slice($rows, 2);

            if (empty($dataRows)) {
                return back()->withErrors(['file' => 'No data found after the first two rows.']);
            }

            $imported = 0;
            $updated = 0;
            $failedRows = [];
            $headerRow = $rows[2] ?? [];

            foreach ($dataRows as $rowIndex => $row) {
                // Map by header (case-insensitive, trimmed)
                $mapped = [];
                foreach ($row as $colIndex => $value) {
                    $header = trim(strtolower($headerRow[$colIndex] ?? ''));
                    if ($header) {
                        $mapped[$header] = trim($value ?? '');
                    }
                }

                $originalRow = $row;

                $validator = Validator::make($mapped, [
                    'load' => ['required', 'string', 'max:100'],
                    'status' => ['required', 'string', 'max:50'],
                    'msft po#' => ['nullable', 'string', 'max:100'],
                    'origin' => ['required', 'string', 'max:50'],
                    'destination' => ['required', 'string', 'max:50'],
                    'ship date' => ['required', 'string'],
                    'deliver date' => ['nullable', 'string'],
                    'sum of pallets' => ['required', 'integer', 'min:0'],
                ]);

                if ($validator->fails()) {
                    $errorMsg = implode('; ', $validator->errors()->all());
                    $failedRows[] = array_merge($originalRow, ['ERROR' => $errorMsg]);
                    continue;
                }

                $validated = $validator->validated();

                // Parse dates
                try {
                    $pickupDateRaw = Carbon::createFromFormat('m/d/Y', $validated['ship date']);
                    if (!$pickupDateRaw)
                        throw new \Exception;
                } catch (\Exception $e) {
                    $failedRows[] = array_merge($originalRow, ['ERROR' => "Invalid 'Ship Date' format (expected m/d/Y)"]);
                    continue;
                }

                $deliveryDateRaw = null;
                if (!empty($validated['deliver date'])) {
                    try {
                        $deliveryDateRaw = Carbon::createFromFormat('m/d/Y', $validated['deliver date']);
                        if (!$deliveryDateRaw)
                            throw new \Exception;
                    } catch (\Exception $e) {
                        $failedRows[] = array_merge($originalRow, ['ERROR' => "Invalid 'Deliver Date' format (expected m/d/Y)"]);
                        continue;
                    }
                }

                // Lookup / create locations
                $pickup = Location::firstOrCreate(
                    ['short_code' => strtoupper($validated['origin'])],
                    [
                        'guid' => \Str::uuid(),
                        'name' => strtoupper($validated['origin']),
                        'address' => 'Unknown Address',
                        'city' => 'Unknown City',
                        'state' => 'Unknown State',
                        'zip' => '00000',
                        'country' => 'XX',
                        'is_active' => false,
                        'type' => 'pickup',
                    ]
                );

                $dc = Location::firstOrCreate(
                    ['short_code' => strtoupper($validated['destination'])],
                    [
                        'guid' => \Str::uuid(),
                        'name' => strtoupper($validated['destination']),
                        'address' => 'Unknown Address',
                        'city' => 'Unknown City',
                        'state' => 'Unknown State',
                        'zip' => '00000',
                        'country' => 'XX',
                        'expected_arrival_time' => '08:00:00',
                        'is_active' => false,
                        'type' => 'distribution_center',
                    ]
                );

                // Time from DC
                $time = $dc->expected_arrival_time
                    ? Carbon::parse($dc->expected_arrival_time)->format('H:i:s')
                    : '00:00:00';

                $pickupDate = $pickupDateRaw->format('Y-m-d') . ' ' . $time;
                $deliveryDate = $deliveryDateRaw ? $deliveryDateRaw->format('Y-m-d') . ' ' . $time : null;

                // Drop date logic
                $dropDate = Carbon::parse($pickupDate)->subDays(2);
                if ($dropDate->isSaturday()) {
                    $dropDate->subDay();
                } elseif ($dropDate->isSunday()) {
                    $dropDate->subDays(2);
                }

                // ────────────────────────────────────────────────
                // Update or create shipment + log changes in note
                // ────────────────────────────────────────────────
                $shipment = Shipment::firstOrNew(['shipment_number' => $validated['load']]);

                $wasExisting = $shipment->exists;

                $oldValues = $shipment->getAttributes(); // snapshot before update

                $shipment->fill([
                    'shipment_number' => $validated['load'],
                    'status' => $validated['status'],
                    'po_number' => $validated['msft po#'] ?? null,
                    'pickup_location_id' => $pickup->id,
                    'dc_location_id' => $dc->id,
                    'carrier_id' => null,
                    'drop_date' => $dropDate,
                    'pickup_date' => $pickupDate,
                    'delivery_date' => $deliveryDate,
                    'rack_qty' => (int) $validated['sum of pallets'],
                ]);

                // Only save if something changed (or new)
                if ($shipment->isDirty() || !$wasExisting) {
                    $shipment->save();

                    $shipment->calculateBol();

                    // ── Add note if updated (not new) ──────────────────────
                    if ($wasExisting) {
                        $changes = [];

                        // Fields to track (add/remove as needed)
                        $trackedFields = [
                            'status' => 'Status',
                            'po_number' => 'PO Number',
                            'pickup_location_id' => 'Pickup Location',
                            'dc_location_id' => 'DC Location',
                            'drop_date' => 'Drop Date',
                            'pickup_date' => 'Pickup Date',
                            'delivery_date' => 'Delivery Date',
                            'rack_qty' => 'Pallets/Rack Qty',
                        ];

                        foreach ($trackedFields as $field => $label) {
                            $old = $oldValues[$field] ?? null;
                            $new = $shipment->$field;

                            // Handle dates & IDs specially
                            if (str_ends_with($field, '_date')) {
                                $old = $old ? Carbon::parse($old)->format('Y-m-d H:i:s') : null;
                                $new = $new ? Carbon::parse($new)->format('Y-m-d H:i:s') : null;
                            } elseif (str_ends_with($field, '_location_id')) {
                                $oldLoc = Location::find($old);
                                $newLoc = Location::find($new);
                                $old = $oldLoc ? $oldLoc->short_code : null;
                                $new = $newLoc ? $newLoc->short_code : null;
                            }

                            if ($old !== $new) {
                                $changes[] = "$label changed from '$old' to '$new'";
                            }
                        }

                        if (!empty($changes)) {
                            $noteContent = "PBI import updated this shipment:\n" . implode("\n", $changes);

                            $shipment->notes()->create([
                                'title' => 'PBI Import Update',
                                'content' => $noteContent,
                                'is_admin' => false,
                                'user_id' => auth()->id() ?? null,
                            ]);
                        }
                    }

                    $wasExisting ? $updated++ : $imported++;
                }
            }

            // ── Handle failed rows (unchanged) ──────────────────────
            if (!empty($failedRows)) {
                // ... your existing TSV generation and session flash ...
            }

            return redirect()->route('admin.shipments.index')
                ->with('success', "$imported new shipment(s) imported, $updated existing updated.");

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to process file: ' . $e->getMessage()]);
        }
    }

    /**
     * Download the failed rows TSV (one-time use)
     */
    public function downloadFailedTsv()
    {
        $content = session('failed_tsv_content');
        $filename = session('failed_tsv_filename', 'failed_shipments.tsv');

        if (! $content) {
            return redirect()->route('admin.shipments.index')
                ->with('error', 'No failed file available to download.');
        }

        // Clear session data after serving
        session()->forget(['failed_tsv_content', 'failed_tsv_filename']);

        return response($content)
            ->header('Content-Type', 'text/tab-separated-values; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    public function sendPaperwork(Request $request, Shipment $shipment): \Inertia\Response
    {
        $shipment->load(['pickupLocation:id,short_code,name', 'dcLocation:id,short_code,name', 'carrier:id,name']);

        $templates = Template::where('model_type', 'App\Models\Location')
            ->where('model_id', $shipment->pickup_location_id)
            ->get();

        return Inertia::render('Admin/Shipments/SendPaperwork', [
            'shipment' => $shipment,
            'templates' => $templates,
        ]);
    }

    public function generateTable(Shipment $shipment, array $columns)
    {
        $html = <<<'HTML'
<table style="border-collapse: collapse; width: 100%; border-width: 1px; border-color: #000000;" border="1">
    <tbody>
        <tr style="background-color: #0b5394;color: #ecf0f1;text-align: center;">
HTML;

        foreach ($columns as $column) {
            switch (strtolower(trim($column))) {
                case 'status':
                    $html .= '<td><strong>Status</strong></td>';
                    break;
                case 'bol':
                    $html .= '<td><strong>BOL</strong></td>';
                    break;
                case 'pickup_location':
                    $html .= '<td><strong>Pickup Location</strong></td>';
                    break;
                case 'shipment_number':
                    $html .= '<td><strong>Shipment Number</strong></td>';
                    break;
                case 'dc_location':
                    $html .= '<td><strong>DC Location</strong></td>';
                    break;
                case 'drop_date':
                    $html .= '<td><strong>Drop Date</strong></td>';
                    break;
                case 'pickup_date':
                    $html .= '<td><strong>Pickup Date</strong></td>';
                    break;
                case 'delivery_date':
                    $html .= '<td><strong>Delivery Date</strong></td>';
                    break;
                case 'po_number':
                    $html .= '<td><strong>PO</strong></td>';
                    break;
                case 'rack_qty':
                    $html .= '<td><strong>Rack Qty</strong></td>';
                    break;
                case 'carrier_code':
                    $html .= '<td><strong>Carrier</strong></td>';
                    break;
                case 'trailer':
                    $html .= '<td><strong>Trailer</strong></td>';
                    break;
                case 'load_bar_qty':
                    $html .= '<td><strong>Load Bars</strong></td>';
                    break;
                case 'strap_qty':
                    $html .= '<td><strong>Straps</strong></td>';
                    break;
                case 'dc_location_address':
                    $html .= '<td><strong>Delivery Address</strong></td>';
                    break;
                default:
                    // Ignore unknown columns
                    break;
            }
        }
        $html .= <<<'HTML'
        </tr>
        <tr style="border-color: #000000; background-color: #ffffff; color: #000000;">
HTML;
        if ($shipment->isConsolidation()) {
            $shipment->load('consolidationShipments');
            $shipments = $shipment->consolidationShipments;
            foreach ($shipments as $consolShipment) {
                foreach ($columns as $column) {
                    switch (strtolower(trim($column))) {
                        case 'status':
                            $html .= '<td>'.($consolShipment->status ?? '').'</td>';
                            break;
                        case 'bol':
                            $html .= '<td>'.($consolShipment->bol ?? '').'</td>';
                            break;
                        case 'pickup_location':
                            $html .= '<td>'.(optional($consolShipment->pickupLocation)->short_code ?? '').'</td>';
                            break;
                        case 'shipment_number':
                            $html .= '<td>'.($consolShipment->shipment_number ?? '').'</td>';
                            break;
                        case 'dc_location':
                            $html .= '<td>'.(optional($consolShipment->dcLocation)->short_code ?? '').'</td>';
                            break;
                        case 'drop_date':
                            $html .= '<td>'.(Carbon::parse($consolShipment->drop_date)->format('m/d/Y') ?? '').'</td>';
                            break;
                        case 'pickup_date':
                            $html .= '<td>'.(Carbon::parse($consolShipment->pickup_date)->format('m/d/Y') ?? '').'</td>';
                            break;
                        case 'delivery_date':
                            $html .= '<td>'.(Carbon::parse($consolShipment->delivery_date)->format('m/d/Y') ?? '').'</td>';
                            break;
                        case 'po_number':
                            $html .= '<td>'.($consolShipment->po_number ?? '').'</td>';
                            break;
                        case 'rack_qty':
                            $html .= '<td>'.($consolShipment->rack_qty ?? '').'</td>';
                            break;
                        case 'carrier_code':
                            $html .= '<td>'.(optional($consolShipment->carrier)->short_code ?? '').'</td>';
                            break;
                        case 'trailer':
                            $html .= '<td>'.($consolShipment->trailer ?? '').'</td>';
                            break;
                        case 'load_bar_qty':
                            $html .= '<td>'.($consolShipment->load_bar_qty ?? '').'</td>';
                            break;
                        case 'strap_qty':
                            $html .= '<td>'.($consolShipment->strap_qty ?? '').'</td>';
                            break;
                        case 'dc_location_address':
                            $html .= '<td>'.(optional($consolShipment->dcLocation)->address ?? '').'</td>';
                            break;
                        default:
                            // Ignore unknown columns
                            break;
                    }
                }
            }

        }
        $html .= <<<'HTML'
        </tr>
    </tbody>
</table>
HTML;

        $shipment->load(['pickupLocation', 'dcLocation', 'carrier']);

        $tableHtml = view('shipments.partials.msft_table_1', ['shipment' => $shipment])->render();

        return response()->json(['table_html' => $tableHtml]);
    }

    /**
     * Process SendPaperwork form and send the selected template to carrier emails.
     */
    public function processSendPaperwork(Request $request, Shipment $shipment): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'template_id' => 'required|exists:templates,id',
            'lrc_file' => 'nullable|file|mimes:pdf|max:10240', // 10MB max, PDF only
            'bol_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $template = Template::findOrFail($data['template_id']);

        $shipment->load(['pickupLocation', 'dcLocation', 'carrier']);

        $replacements = [
            'table' => '',
            'status' => $shipment->status ?? '',
            'shipment_number' => $shipment->shipment_number ?? 'XXXX',
            'bol' => $shipment->bol ?? 'XXXX',
            'po_number' => $shipment->po_number ?? '',
            'pickup_location' => optional($shipment->pickupLocation)->short_code ?? '',
            'pickup_location_name' => optional($shipment->pickupLocation)->name ?? '',
            'dc_location' => optional($shipment->dcLocation)->short_code ?? '',
            'dc_location_name' => optional($shipment->dcLocation)->name ?? '',
            'dc_location_address' => optional($shipment->dcLocation)->address ?? '',
            'carrier_code' => optional($shipment->carrier)->short_code ?? '',
            'trailer' => $shipment->trailer ?? 'XXXX',
            'load_bar_qty' => $shipment->load_bar_qty ?? '0',
            'rack_qty' => $shipment->rack_qty ?? '0',
            'strap_qty' => $shipment->strap_qty ?? '0',
            'drop_date' => $shipment->drop_date ? Carbon::parse($shipment->drop_date)->format('m/d/Y') : '',
            'pickup_date' => $shipment->pickup_date ? Carbon::parse($shipment->pickup_date)->format('m/d/Y') : '',
            'delivery_date' => $shipment->delivery_date ? Carbon::parse($shipment->delivery_date)->format('m/d/Y') : '',
        ];

        $renderPlaceholders = function (string $text) use ($replacements): string {
            return preg_replace_callback('/\{\{?\s*([^\}\s]+)\s*\}?\}/', function ($matches) use ($replacements) {
                $key = strtolower(trim($matches[1]));

                return $replacements[$key] ?? $matches[0];
            }, $text);
        };

        $subject = $renderPlaceholders((string) $template->subject);
        $body = $renderPlaceholders((string) $template->message);

        $recipients = [];

        $carrier = $shipment->carrier;

        if ($carrier && isset($carrier->emails) && ! empty($carrier->emails)) {
            $input = str_replace(',', ';', $carrier->emails);
            $parts = array_map('trim', explode(';', $input));
            $emails = [];

            foreach ($parts as $part) {
                if (empty($part)) {
                    continue;
                }

                // "Name" <email@domain.com>
                if (preg_match('/<([^>]+)>/', $part, $matches)) {
                    $email = trim($matches[1]);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $email;
                    }
                }
                // Plain email
                elseif (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $part;
                }
            }

            $recipients = array_values(array_unique($emails));
        }

        if (empty($recipients)) {
            return back()->withErrors(['recipients' => 'No valid recipient emails found for the shipment carrier.']);
        }

        try {
            Mail::send([], [], function ($message) use ($recipients, $subject, $body, $request) {
                $message->to($recipients);
                $message->subject($subject);
                $message->html($body);

                // Attach LRC file if uploaded
                if ($request->hasFile('lrc_file') && $request->file('lrc_file')->isValid()) {
                    $message->attach(
                        $request->file('lrc_file')->getRealPath(),
                        [
                            'as' => $request->file('lrc_file')->getClientOriginalName(),
                            'mime' => $request->file('lrc_file')->getMimeType(),
                        ]
                    );
                }

                // Attach BOL file if uploaded
                if ($request->hasFile('bol_file') && $request->file('bol_file')->isValid()) {
                    $message->attach(
                        $request->file('bol_file')->getRealPath(),
                        [
                            'as' => $request->file('bol_file')->getClientOriginalName(),
                            'mime' => $request->file('bol_file')->getMimeType(),
                        ]
                    );
                }
            });

            if ($shipment->isFillable('paperwork_sent')) {
                $shipment->update(['paperwork_sent' => now()]);
            }

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'Paperwork sent successfully.');
        } catch (\Exception $e) {
            \Log::error('Paperwork email failed: '.$e->getMessage());

            return back()->withErrors(['email' => 'Failed to send email: '.$e->getMessage()]);
        }
    }

    public function calculateBol(Shipment $shipment): \Illuminate\Http\RedirectResponse
    {
        try {
            $shipment->calculateBol();

            return redirect()->route('admin.shipments.show', $shipment)
                ->with('success', 'BOL calculated successfully.');
        } catch (\Exception $e) {
            \Log::error("Failed to calculate BOL for shipment {$shipment->id}: ".$e->getMessage());

            return back()->withErrors(['bol' => 'Failed to calculate BOL: '.$e->getMessage()]);
        }
    }
}
