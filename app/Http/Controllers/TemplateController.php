<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Location;
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

        return Inertia::render('Admin/Templates/Create', [
            'carriers' => $carriers,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:templates,name',
            'model_type' => 'required|in:carrier,location',
            'model_id' => 'required|integer',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Validate that model_id exists for the given model_type
        $validated['model_type'] = $validated['model_type'] === 'carrier'
            ? 'App\\Models\\Carrier'
            : 'App\\Models\\Location';
        $modelClass = $validated['model_type'];
        if (! class_exists($modelClass) || ! $modelClass::find($validated['model_id'])) {
            return back()->withErrors(['model_id' => 'Selected model does not exist.']);
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

        return Inertia::render('Admin/Templates/Edit', [
            'template' => $template,
            'carriers' => $carriers,
            'locations' => $locations,
        ]);
    }

    public function update(Request $request, Template $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:templates,name,'.$template->id,
            'model_type' => 'required|in:carrier,location',
            'model_id' => 'required|integer',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Validate that model_id exists for the given model_type
        $validated['model_type'] = $validated['model_type'] === 'carrier'
            ? 'App\\Models\\Carrier'
            : 'App\\Models\\Location';
        $modelClass = $validated['model_type'];
        if (! class_exists($modelClass) || ! $modelClass::find($validated['model_id'])) {
            return back()->withErrors(['model_id' => 'Selected model does not exist.']);
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
}
