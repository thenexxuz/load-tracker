<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;

class CarrierController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:25',
            'search' => 'nullable|string|max:500',
        ]);

        if (auth()->user()->hasRole('carrier')) {
            $carriers = Carrier::where('id', auth()->user()->carrier_id)
                ->paginate($validated['per_page'] ?? 15)
                ->withQueryString();
        } else {
            $carriers = Carrier::query()
                ->when($validated['search'], function ($q, $search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('short_code', 'like', "%{$search}%")
                        ->orWhere('wt_code', 'like', "%{$search}%")
                        ->orWhere('emails', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->paginate($validated['per_page'] ?? 15)
                ->withQueryString();
        }

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
        if ($carrier->shipments()->exists()) {
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
                $q->where('short_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('wt_code', 'like', "%{$search}%")
                    ->orWhere('emails', 'like', "%{$search}%");
            });

        $carriers = $query->get([
            'short_code',
            'wt_code',
            'name',
            'emails',
            'is_active',
            'created_at',
            'updated_at',
        ]);

        // Prepare export data
        $exportData = $carriers->map(function ($carrier) {
            return [
                'short_code' => $carrier->short_code,
                'wt_code' => $carrier->wt_code ?? '',
                'name' => $carrier->name ?? '',
                'emails' => $carrier->emails ? implode('; ', $carrier->email_list) : '',
                'is_active' => $carrier->is_active ? '1' : '0',
                'created_at' => $carrier->created_at?->format('Y-m-d H:i:s') ?? '',
                'updated_at' => $carrier->updated_at?->format('Y-m-d H:i:s') ?? '',
            ];
        });

        // Headers (keep order consistent with import if you add one later)
        $headers = [
            'short_code',
            'wt_code',
            'name',
            'emails',
            'is_active',
            'created_at',
            'updated_at',
        ];

        // Build TSV
        $tsv = implode("\t", $headers)."\n";

        foreach ($exportData as $row) {
            $tsv .= implode("\t", array_map(function ($value) {
                $value = str_replace(["\t", "\n", "\r"], ['\\t', '\\n', '\\r'], $value ?? '');
                if (str_contains($value, "\t") || str_contains($value, '"') || str_contains($value, "\n")) {
                    $value = '"'.str_replace('"', '""', $value).'"';
                }

                return $value;
            }, $row))."\n";
        }

        $filename = 'carriers_export_'.now()->format('Y-m-d_His').'.tsv';

        return response($tsv)
            ->header('Content-Type', 'text/tab-separated-values; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    /**
     * Import carriers from uploaded TSV file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt,tsv,csv|max:10240', // 10MB max
        ]);

        $file = $request->file('file');

        try {
            $csv = Reader::createFromPath($file->getRealPath(), 'r');
            $csv->setDelimiter("\t");
            $csv->setHeaderOffset(0); // first row = headers

            $records = $csv->getRecords();

            $imported = 0;
            $errors = [];

            foreach ($records as $offset => $row) {
                $data = (array) $row;

                $validator = Validator::make($data, [
                    'short_code' => ['required', 'string', 'max:50'],
                    'wt_code' => ['nullable', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'emails' => ['nullable', 'string'],
                    'is_active' => ['nullable', 'in:0,1,true,false,yes,no,active,inactive'],
                    'notes' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $errors[] = 'Row '.($offset + 2).': '.implode(', ', $validator->errors()->all());

                    continue;
                }

                // Normalize is_active
                $isActive = $data['is_active'] ?? true;
                if (is_string($isActive)) {
                    $isActive = in_array(strtolower($isActive), ['1', 'true', 'yes', 'active']);
                }

                // Convert emails to comma-separated (to match your current accessor)
                $emails = $data['emails'] ?? '';
                $emails = str_replace(';', ',', $emails); // normalize semicolon → comma
                $emails = trim($emails, ',');

                Carrier::updateOrCreate(
                    ['short_code' => $data['short_code']],
                    [
                        'guid' => (string) Str::uuid(),
                        'wt_code' => $data['wt_code'] ?? null,
                        'name' => $data['name'],
                        'emails' => $emails ?: null,
                        'is_active' => $isActive,
                        'notes' => $data['notes'] ?? null,
                    ]
                );

                $imported++;
            }

            $message = "$imported carrier(s) imported/updated successfully.";
            if ($errors) {
                $message .= ' '.count($errors).' rows skipped due to errors.';
            }

            if ($imported === 0 && $errors) {
                return back()->withErrors(['file' => implode('; ', $errors)]);
            }

            return redirect()->route('admin.carriers.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to process file: '.$e->getMessage()]);
        }
    }
}
