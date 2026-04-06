<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\ScheduledItem;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'model_type' => 'required|in:carrier,location,scheduled_item,template_token',
            'model_id' => 'nullable',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Validate that model_id exists for the given model_type (except for model-less types)
        $modelTypeMap = [
            'carrier' => 'App\\Models\\Carrier',
            'location' => 'App\\Models\\Location',
            'scheduled_item' => 'App\\Models\\ScheduledItem',
            'template_token' => 'App\\Models\\Template',
        ];
        $validated['model_type'] = $modelTypeMap[$validated['model_type']];
        $modelClass = $validated['model_type'];

        // Only validate model_id exists if this type requires a related model.
        if (! in_array($modelClass, ['App\\Models\\ScheduledItem', 'App\\Models\\Template'], true)) {
            if (! isset($validated['model_id']) || is_null($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }

            if (! class_exists($modelClass) || ! $modelClass::find($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }
        } else {
            // For model-less template types, set model_id to null.
            $validated['model_id'] = null;
        }

        if ($modelClass === Template::class) {
            $validated['subject'] = null;

            $this->ensureNoCircularTokenNesting(
                (string) $validated['name'],
                (string) ($validated['message'] ?? ''),
            );
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
            'model_type' => 'required|in:carrier,location,scheduled_item,template_token',
            'model_id' => 'nullable',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Validate that model_id exists for the given model_type (except for model-less types)
        $modelTypeMap = [
            'carrier' => 'App\\Models\\Carrier',
            'location' => 'App\\Models\\Location',
            'scheduled_item' => 'App\\Models\\ScheduledItem',
            'template_token' => 'App\\Models\\Template',
        ];
        $validated['model_type'] = $modelTypeMap[$validated['model_type']];
        $modelClass = $validated['model_type'];

        // Only validate model_id exists if this type requires a related model.
        if (! in_array($modelClass, ['App\\Models\\ScheduledItem', 'App\\Models\\Template'], true)) {
            if (! isset($validated['model_id']) || is_null($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }

            if (! class_exists($modelClass) || ! $modelClass::find($validated['model_id'])) {
                return back()->withErrors(['model_id' => 'Selected model does not exist.']);
            }
        } else {
            // For model-less template types, set model_id to null.
            $validated['model_id'] = null;
        }

        if ($modelClass === Template::class) {
            $validated['subject'] = null;

            $this->ensureNoCircularTokenNesting(
                (string) $validated['name'],
                (string) ($validated['message'] ?? ''),
                $template,
            );
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

    private function ensureNoCircularTokenNesting(string $tokenName, string $tokenMessage, ?Template $currentTemplate = null): void
    {
        $normalizedTokenName = strtolower(trim($tokenName));

        if ($normalizedTokenName === '') {
            return;
        }

        $tokenMessages = Template::query()
            ->where('model_type', Template::class)
            ->whereNull('model_id')
            ->when($currentTemplate, fn ($query) => $query->whereKeyNot($currentTemplate->getKey()))
            ->get(['name', 'message'])
            ->mapWithKeys(function (Template $token): array {
                $key = strtolower(trim((string) $token->name));

                if ($key === '') {
                    return [];
                }

                return [$key => (string) ($token->message ?? '')];
            })
            ->all();

        $tokenMessages[$normalizedTokenName] = $tokenMessage;

        try {
            Template::resolveTemplateTokenReplacements([], $tokenMessages);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'message' => "Circular template token nesting detected at token '{{{$exception->getMessage()}}}'.",
            ]);
        }
    }
}
