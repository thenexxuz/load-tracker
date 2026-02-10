<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:25',
            'search' => 'nullable|string|max:500',
        ]);

        if (auth()->user()->hasRole('carrier')) {
            $query = Shipment::query()
                ->where('carrier_id', auth()->user()->carrier_id)
                ->with(['pickupLocation', 'dcLocation', 'carrier']);
        } else {
            $query = Shipment::query()
                ->with(['pickupLocation', 'dcLocation', 'carrier']);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('bol', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%");
            });
        }

        // Exclude statuses
        if ($excludedStatuses = $request->input('excluded_statuses')) {
            $query->whereNotIn('status', (array) $excludedStatuses);
        }

        // Exclude shippers
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
        if (is_array($excludedCarriers) && count($excludedCarriers) === count(Carrier::pluck('name')->unique()->toArray())) {
            $query->whereNull('carrier_id');
        }
        // Normal case: exclude the selected carriers
        elseif (! empty($excludedCarriers)) {
            $query->whereNotIn('carrier_id', function ($sub) use ($excludedCarriers) {
                $sub->select('id')
                    ->from('carriers')
                    ->whereIn('name', $excludedCarriers);
            });
        }

        // Drop Date filter
        $dropStart = $request->input('drop_start');
        $dropEnd = $request->input('drop_end');

        // If both are provided → between those two dates (inclusive)
        if ($dropStart && $dropEnd) {
            $query->whereDate('drop_date', '>=', $dropStart)
                ->whereDate('drop_date', '<=', $dropEnd);
        }
        // Only start date → from that date onward
        elseif ($dropStart) {
            $query->whereDate('drop_date', '>=', $dropStart);
        }
        // Only end date → up to that date
        elseif ($dropEnd) {
            $query->whereDate('drop_date', '<=', $dropEnd);
        }

        $shipments = $query->latest()->paginate($validated['per_page'] ?? 15)->withQueryString();

        return Inertia::render('Admin/Shipments/Index', [
            'shipments' => $shipments,
            'statuses' => Shipment::select('status')->distinct()->pluck('status')->sort()->values(),
            'all_shipper_codes' => Location::where('type', 'pickup')->pluck('short_code')->unique()->sort()->values(),
            'all_dc_codes' => Location::where('type', 'distribution_center')->pluck('short_code')->unique()->sort()->values(),
            'all_carrier_names' => Carrier::pluck('name')->unique()->sort()->values(),
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
        $shipment->load(['pickupLocation:id,short_code,name', 'dcLocation:id,short_code,name', 'carrier:id,name']);

        return Inertia::render('Admin/Shipments/Show', [
            'shipment' => $shipment,
        ]);
    }

    public function edit(Shipment $shipment)
    {
        $shipment->load(['pickupLocation', 'dcLocation', 'carrier']);

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

                // Preserve original row data for failed TSV
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

                // Parse dates as m/d/Y
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
                $pickup = Location::where('short_code', $validated['origin'])->first();
                $dc = Location::where('short_code', $validated['destination'])->first();

                if (! $pickup) {
                    $pickup = new Location;
                    $pickup->short_code = $validated['origin'];
                    $pickup->name = $validated['origin'];
                    $pickup->address = 'Unknown Address';
                    $pickup->city = 'Unknown City';
                    $pickup->state = 'Unknown State';
                    $pickup->zip = '00000';
                    $pickup->country = 'XX';
                    $pickup->is_active = false;
                    $pickup->type = 'pickup';
                    $pickup->save();
                }
                if (! $dc) {
                    $dc = new Location;
                    $dc->short_code = $validated['destination'];
                    $dc->name = $validated['destination'];
                    $dc->address = 'Unknown Address';
                    $dc->city = 'Unknown City';
                    $dc->state = 'Unknown State';
                    $dc->zip = '00000';
                    $dc->country = 'XX';
                    $dc->expected_arrival_time = '08:00:00';
                    $dc->is_active = false;
                    $dc->type = 'distribution_center';
                    $dc->save();
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
                    'pickup_location_id' => $pickup->id,
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
            // Handle failed rows
            // ────────────────────────────────────────────────
            if (! empty($failedRows)) {
                // Build TSV content
                $tsvHeaders = array_merge($headerRow, ['ERROR']);
                $writer = Writer::createFromString('');
                $writer->setDelimiter("\t");
                $writer->insertOne($tsvHeaders);

                foreach ($failedRows as $failedRow) {
                    $writer->insertOne($failedRow);
                }

                $tsvContent = (string) $writer;
                $filename = 'failed_shipments_'.now()->format('Y-m-d_His').'.tsv';

                // Store in session for one-time download
                session()->flash('failed_tsv_content', $tsvContent);
                session()->flash('failed_tsv_filename', $filename);

                return redirect()->route('admin.shipments.index')
                    ->with('warning', "$imported shipments imported successfully. ".count($failedRows).' rows failed — download the failed rows below.');
            }

            // All good
            return redirect()->route('admin.shipments.index')
                ->with('success', "$imported shipment(s) imported successfully.");

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to process file: '.$e->getMessage()]);
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
}
