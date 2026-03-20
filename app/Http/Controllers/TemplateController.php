<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\ScheduledItem;
use App\Models\Template;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(): Response
    {
        $templates = Template::with('model')
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        $carriers = Carrier::orderBy('name')->get(['id', 'name', 'short_code']);
        $locations = Location::orderBy('short_code')->get(['id', 'short_code', 'name']);
        $scheduledItems = ScheduledItem::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Templates/Create', [
            'carriers' => $carriers,
            'locations' => $locations,
            'scheduledItems' => $scheduledItems,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:templates,name',
            'model_type' => 'required|in:carrier,location,scheduled_item',
            'model_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Validate that model_id exists for the given model_type (except for scheduled_item)
        $modelTypeMap = [
            'carrier' => 'App\\Models\\Carrier',
            'location' => 'App\\Models\\Location',
            'scheduled_item' => 'App\\Models\\ScheduledItem',
        ];
        $validated['model_type'] = $modelTypeMap[$validated['model_type']];
        $modelClass = $validated['model_type'];

        // Only validate model_id exists if not scheduled_item
        if ($modelClass !== 'App\\Models\\ScheduledItem') {
            if (! isset($validated['model_id']) || is_null($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }
            if (! class_exists($modelClass) || ! $modelClass::find($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }
        } else {
            // For scheduled_item, set model_id to null
            $validated['model_id'] = null;
        }

        Template::create($validated);

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function show(Template $template): Response
    {
        $template->load('model');

        return Inertia::render('Admin/Templates/Show', [
            'template' => $template,
        ]);
    }

    public function edit(Template $template): Response
    {
        $template->load('model');

        $carriers = Carrier::orderBy('name')->get(['id', 'name', 'short_code']);
        $locations = Location::orderBy('short_code')->get(['id', 'short_code', 'name']);
        $scheduledItems = ScheduledItem::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Templates/Edit', [
            'template' => $template,
            'carriers' => $carriers,
            'locations' => $locations,
            'scheduledItems' => $scheduledItems,
        ]);
    }

    public function update(Request $request, Template $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:templates,name,'.$template->id,
            'model_type' => 'required|in:carrier,location,scheduled_item',
            'model_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Validate that model_id exists for the given model_type (except for scheduled_item)
        $modelTypeMap = [
            'carrier' => 'App\\Models\\Carrier',
            'location' => 'App\\Models\\Location',
            'scheduled_item' => 'App\\Models\\ScheduledItem',
        ];
        $validated['model_type'] = $modelTypeMap[$validated['model_type']];
        $modelClass = $validated['model_type'];

        // Only validate model_id exists if not scheduled_item
        if ($modelClass !== 'App\\Models\\ScheduledItem') {
            if (! isset($validated['model_id']) || is_null($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }
            if (! class_exists($modelClass) || ! $modelClass::find($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }
        } else {
            // For scheduled_item, set model_id to null
            $validated['model_id'] = null;
        }

        $template->update($validated);

        return redirect()->route('admin.templates.show', $template->id)
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(Template $template)
    {
        $template->delete();

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template deleted successfully.');
    }

    public function export()
    {
        $templates = Template::all();

        $callback = function () use ($templates) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Content', 'Type', 'Created At']); // adjust columns as needed

            foreach ($templates as $template) {
                fputcsv($file, [
                    $template->id,
                    $template->name,
                    $template->content ?? $template->body ?? $template->html ?? '', // adjust field name
                    $template->type ?? 'general', // if you have a type field
                    $template->created_at,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="templates-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // up to 10MB
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle); // skip header row

        $imported = 0;
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[1])) {
                continue;
            } // skip if name is empty

            $template = Template::updateOrCreate(
                ['name' => trim($row[1])],
                [
                    'content' => trim($row[2] ?? ''), // adjust field name
                    'type' => trim($row[3] ?? 'general'),
                    // add more fields if needed
                ]
            );

            if ($template->wasRecentlyCreated) {
                $imported++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        return back()->with('success', "Import complete: {$imported} new templates added, {$updated} updated.");
    }
}
