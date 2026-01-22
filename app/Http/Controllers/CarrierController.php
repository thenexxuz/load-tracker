<?php

namespace App\Http\Controllers;

use League\Csv\Reader;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\Carrier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CarrierController extends Controller
{
    public function index()
    {
        $carriers = Carrier::query()
            ->when(request('search'), fn($q, $search) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Admin/Carriers/Index', [
            'carriers' => $carriers,
            'filters'  => request()->only('search'),
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
        if ($carrier->loads()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete carrier in use.']);
        }

        $carrier->delete();

        return redirect()->route('admin.carriers.index')
            ->with('success', 'Carrier deleted successfully.');
    }

    /**
     * Export all (or filtered) carriers as TSV.
     */
    public function export(Request $request)
    {
        $query = Carrier::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });

        $carriers = $query->get([
            'short_code',
            'name',
            'contact_name',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'is_active',
            'notes',
            'created_at',
            'updated_at',
        ]);

        // Transform for clean TSV output
        $exportData = $carriers->map(function ($carrier) {
            return [
                'short_code'    => $carrier->short_code,
                'name'          => $carrier->name ?? '',
                'contact_name'  => $carrier->contact_name ?? '',
                'email'         => $carrier->email ?? '',
                'phone'         => $carrier->phone ?? '',
                'address'       => $carrier->address ?? '',
                'city'          => $carrier->city ?? '',
                'state'         => $carrier->state ?? '',
                'zip'           => $carrier->zip ?? '',
                'country'       => $carrier->country ?? 'US',
                'is_active'     => $carrier->is_active ? '1' : '0',
                'notes'         => $carrier->notes ?? '',
                'created_at'    => $carrier->created_at?->format('Y-m-d H:i:s') ?? '',
                'updated_at'    => $carrier->updated_at?->format('Y-m-d H:i:s') ?? '',
            ];
        });

        // Headers
        $headers = [
            'short_code', 'name', 'contact_name', 'email', 'phone',
            'address', 'city', 'state', 'zip', 'country',
            'is_active', 'notes', 'created_at', 'updated_at'
        ];

        // Build TSV content
        $tsv = implode("\t", $headers) . "\n";

        foreach ($exportData as $row) {
            $tsv .= implode("\t", array_map(function ($value) {
                $value = str_replace(["\t", "\n", "\r"], ['\\t', '\\n', '\\r'], $value ?? '');
                if (str_contains($value, "\t") || str_contains($value, '"') || str_contains($value, "\n")) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
                return $value;
            }, $row)) . "\n";
        }

        $filename = 'carriers_export_' . now()->format('Y-m-d_His') . '.tsv';

        return response($tsv)
            ->header('Content-Type', 'text/tab-separated-values')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    /**
     * Import carriers from uploaded TSV file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt,tsv|max:10240', // max 10MB
        ]);

        $file = $request->file('file');

        try {
            $csv = Reader::createFromPath($file->getRealPath(), 'r');
            $csv->setDelimiter("\t");
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();

            $imported = 0;
            $errors   = [];

            foreach ($records as $offset => $row) {
                $data = (array) $row;

                $validator = Validator::make($data, [
                    'short_code'   => ['required', 'string', 'max:50'],
                    'name'         => ['required', 'string', 'max:255'],
                    'contact_name' => ['nullable', 'string', 'max:255'],
                    'email'        => ['nullable', 'email', 'max:255'],
                    'phone'        => ['nullable', 'string', 'max:50'],
                    'address'      => ['nullable', 'string', 'max:255'],
                    'city'         => ['nullable', 'string', 'max:100'],
                    'state'        => ['nullable', 'string', 'size:2'],
                    'zip'          => ['nullable', 'string', 'max:20'],
                    'country'      => ['nullable', 'string', 'size:2'],
                    'is_active'    => ['nullable', 'boolean'],
                    'notes'        => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $errors[] = "Row " . ($offset + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Normalize country
                $data['country'] = strtoupper($data['country'] ?? 'US');

                // is_active as boolean
                $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

                Carrier::updateOrCreate(
                    ['short_code' => $data['short_code']],
                    [
                        'name'         => $data['name'],
                        'contact_name' => $data['contact_name'] ?? null,
                        'email'        => $data['email'] ?? null,
                        'phone'        => $data['phone'] ?? null,
                        'address'      => $data['address'] ?? null,
                        'city'         => $data['city'] ?? null,
                        'state'        => $data['state'] ?? null,
                        'zip'          => $data['zip'] ?? null,
                        'country'      => $data['country'],
                        'is_active'    => $data['is_active'],
                        'notes'        => $data['notes'] ?? null,
                    ]
                );

                $imported++;
            }

            if ($imported === 0 && !empty($errors)) {
                return back()->withErrors(['file' => 'No valid rows imported. Errors: ' . implode('; ', $errors)]);
            }

            $message = "$imported carrier(s) imported/updated successfully.";
            if (!empty($errors)) {
                $message .= ' ' . count($errors) . ' rows skipped due to errors.';
            }

            return redirect()->route('admin.carriers.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to process file: ' . $e->getMessage()]);
        }
    }
}
