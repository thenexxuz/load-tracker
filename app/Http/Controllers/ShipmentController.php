<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
            'shipment_number' => 'unique:shipments,shipment_number,'.$shipment->id,
            'bol' => 'nullable|string|max:100',
            'po_number' => 'nullable|string|max:100',
            'status' => 'required|string',
            'shipper_location_id' => 'required|exists:locations,id',
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
            'on_site' => 'nullable|boolean',
            'shipped' => 'nullable|boolean',
            'recycling_sent' => 'nullable|boolean',
            'paperwork_sent' => 'nullable|boolean',
            'delivery_alert_sent' => 'nullable|boolean',
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

    /**
     * Import shipments from Power BI XLSX file
     * - Skips first two rows
     * - Uses third row as headers
     * - Failed rows are collected and returned as downloadable TSV
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
            $failedRows = [];
            $headerRow = $rows[2] ?? []; // original headers for failed TSV

            foreach ($dataRows as $rowIndex => $row) {
                // Map by header (case-insensitive, trimmed)
                $mapped = [];
                foreach ($row as $colIndex => $value) {
                    $header = trim(strtolower($headerRow[$colIndex] ?? ''));
                    if ($header) {
                        $mapped[$header] = trim($value ?? '');
                    }
                }

                // Add original row data for failed TSV (preserves original order/values)
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

                // Parse dates strictly as m/d/Y
                try {
                    $pickupDateRaw = Carbon::createFromFormat('m/d/Y', $validated['ship date']);
                    if (! $pickupDateRaw) {
                        throw new \Exception;
                    }
                } catch (\Exception $e) {
                    $failedRows[] = array_merge($originalRow, ['ERROR' => "Invalid 'Ship Date' format (expected m/d/Y)"]);

                    continue;
                }

                $deliveryDateRaw = null;
                if (! empty($validated['deliver date'])) {
                    try {
                        $deliveryDateRaw = Carbon::createFromFormat('m/d/Y', $validated['deliver date']);
                        if (! $deliveryDateRaw) {
                            throw new \Exception;
                        }
                    } catch (\Exception $e) {
                        $failedRows[] = array_merge($originalRow, ['ERROR' => "Invalid 'Deliver Date' format (expected m/d/Y)"]);

                        continue;
                    }
                }

                // Lookup locations
                $shipper = Location::where('short_code', $validated['origin'])->first();
                $dc = Location::where('short_code', $validated['destination'])->first();

                if (! $shipper) {
                    $failedRows[] = array_merge($originalRow, ['ERROR' => "Origin location '{$validated['origin']}' not found"]);

                    continue;
                }
                if (! $dc) {
                    $failedRows[] = array_merge($originalRow, ['ERROR' => "Destination location '{$validated['destination']}' not found"]);

                    continue;
                }

                // Time from DC expected arrival (if exists)
                $time = $dc->expected_arrival_time
                    ? Carbon::parse($dc->expected_arrival_time)->format('H:i:s')
                    : '00:00:00';

                $pickupDate = $pickupDateRaw->format('Y-m-d').' '.$time;
                $deliveryDate = $deliveryDateRaw ? $deliveryDateRaw->format('Y-m-d').' '.$time : null;

                // Drop date: 2 days before pickup, adjust to Friday if weekend
                $dropDate = Carbon::parse($pickupDate)->subDays(2);
                if ($dropDate->isSaturday()) {
                    $dropDate->subDay();
                } elseif ($dropDate->isSunday()) {
                    $dropDate->subDays(2);
                }

                // Create shipment
                Shipment::create([
                    'shipment_number' => $validated['load'],
                    'status' => $validated['status'],
                    'po_number' => $validated['msft po#'] ?? null,
                    'shipper_location_id' => $shipper->id,
                    'dc_location_id' => $dc->id,
                    'carrier_id' => null,
                    'drop_date' => $dropDate,
                    'pickup_date' => $pickupDate,
                    'delivery_date' => $deliveryDate,
                    'rack_qty' => (int) $validated['sum of pallets'],
                ]);

                $imported++;
            }

            // ────────────────────────────────────────────────
// If there are failed rows → generate and return TSV download
// ────────────────────────────────────────────────
            if (!empty($failedRows)) {
                // Prepend original headers + 'ERROR' column
                $tsvHeaders = array_merge($headerRow, ['ERROR']);

                // Create CSV writer with tab delimiter
                $writer = Writer::createFromString('');
                $writer->setDelimiter("\t");
                $writer->insertOne($tsvHeaders);

                foreach ($failedRows as $failedRow) {
                    $writer->insertOne($failedRow);
                }

                $tsvContent = (string) $writer;

                $filename = 'failed_shipments_' . now()->format('Y-m-d_His') . '.tsv';

                // Flash warning message BEFORE returning the file
                session()->flash('warning', "$imported shipments imported successfully. " . count($failedRows) . " rows failed and are included in the downloaded TSV file.");

                return response($tsvContent)
                    ->header('Content-Type', 'text/tab-separated-values; charset=utf-8')
                    ->header('Content-Disposition', "attachment; filename=\"$filename\"");
            }

            // No failures → normal success redirect
            return redirect()->route('admin.shipments.index')
                ->with('success', "$imported shipment(s) imported successfully.");

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to process file: '.$e->getMessage()]);
        }
    }
}
