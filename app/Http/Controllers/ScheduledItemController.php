<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\ScheduledItem;
use App\Models\Template;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledItemController extends Controller
{
    public function index(): Response
    {
        $scheduledItems = ScheduledItem::with(['schedulable', 'template'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/ScheduledItems/Index', [
            'scheduledItems' => $scheduledItems,
        ]);
    }

    public function create(): Response
    {
        $carriers = Carrier::orderBy('name')->get(['id', 'name', 'short_code']);
        $templates = Template::orderBy('name')->get(['id', 'name', 'model_type']);

        return Inertia::render('Admin/ScheduledItems/Create', [
            'carriers' => $carriers,
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'schedule_time' => 'required|date_format:H:i',
            'schedule_day_of_week' => 'nullable|integer|between:0,6',
            'schedule_day_of_month' => 'nullable|integer|between:1,31',
            'template_id' => 'nullable|exists:templates,id',
            'apply_to_all' => 'boolean',
            'schedulable_type' => 'required|in:carrier',
            'schedulable_id' => 'nullable|integer',
        ]);

        $applyToAll = $validated['apply_to_all'] ?? false;

        // Validate that the schedulable model exists if not applying to all
        if (! $applyToAll) {
            if (! isset($validated['schedulable_id']) || is_null($validated['schedulable_id'])) {
                return back()->withErrors(['schedulable_id' => 'Please select a target or apply to all.']);
            }

            $schedulableType = $validated['schedulable_type'] === 'carrier' ? 'App\\Models\\Carrier' : null;
            if (! $schedulableType || ! class_exists($schedulableType) || ! $schedulableType::find($validated['schedulable_id'])) {
                return back()->withErrors(['schedulable_id' => 'Selected model does not exist.']);
            }
            $validated['schedulable_type'] = $schedulableType;
        } else {
            $validated['schedulable_id'] = null;
            $validated['schedulable_type'] = $validated['schedulable_type'] === 'carrier' ? 'App\\Models\\Carrier' : null;
        }

        // Validate schedule requirements based on type
        if ($validated['schedule_type'] === 'weekly' && ! isset($validated['schedule_day_of_week'])) {
            return back()->withErrors(['schedule_day_of_week' => 'Day of week is required for weekly schedules.']);
        }

        if ($validated['schedule_type'] === 'monthly' && ! isset($validated['schedule_day_of_month'])) {
            return back()->withErrors(['schedule_day_of_month' => 'Day of month is required for monthly schedules.']);
        }

        ScheduledItem::create($validated);

        return redirect()->route('admin.scheduled-items.index')
            ->with('success', 'Scheduled item created successfully.');
    }

    public function show(ScheduledItem $scheduledItem): Response
    {
        $scheduledItem->load(['schedulable', 'template']);

        return Inertia::render('Admin/ScheduledItems/Show', [
            'scheduledItem' => $scheduledItem,
        ]);
    }

    public function edit(ScheduledItem $scheduledItem): Response
    {
        $scheduledItem->load(['schedulable', 'template']);

        $carriers = Carrier::orderBy('name')->get(['id', 'name', 'short_code']);
        $templates = Template::orderBy('name')->get(['id', 'name', 'model_type']);

        return Inertia::render('Admin/ScheduledItems/Edit', [
            'scheduledItem' => $scheduledItem,
            'carriers' => $carriers,
            'templates' => $templates,
        ]);
    }

    public function update(Request $request, ScheduledItem $scheduledItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'schedule_time' => 'required|date_format:H:i',
            'schedule_day_of_week' => 'nullable|integer|between:0,6',
            'schedule_day_of_month' => 'nullable|integer|between:1,31',
            'template_id' => 'nullable|exists:templates,id',
            'apply_to_all' => 'boolean',
            'schedulable_type' => 'required|in:carrier',
            'schedulable_id' => 'nullable|integer',
        ]);

        $applyToAll = $validated['apply_to_all'] ?? false;

        // Validate that the schedulable model exists if not applying to all
        if (! $applyToAll) {
            if (! isset($validated['schedulable_id']) || is_null($validated['schedulable_id'])) {
                return back()->withErrors(['schedulable_id' => 'Please select a target or apply to all.']);
            }

            $schedulableType = $validated['schedulable_type'] === 'carrier' ? 'App\\Models\\Carrier' : null;
            if (! $schedulableType || ! class_exists($schedulableType) || ! $schedulableType::find($validated['schedulable_id'])) {
                return back()->withErrors(['schedulable_id' => 'Selected model does not exist.']);
            }
            $validated['schedulable_type'] = $schedulableType;
        } else {
            $validated['schedulable_id'] = null;
            $validated['schedulable_type'] = $validated['schedulable_type'] === 'carrier' ? 'App\\Models\\Carrier' : null;
        }

        // Validate schedule requirements based on type
        if ($validated['schedule_type'] === 'weekly' && ! isset($validated['schedule_day_of_week'])) {
            return back()->withErrors(['schedule_day_of_week' => 'Day of week is required for weekly schedules.']);
        }

        if ($validated['schedule_type'] === 'monthly' && ! isset($validated['schedule_day_of_month'])) {
            return back()->withErrors(['schedule_day_of_month' => 'Day of month is required for monthly schedules.']);
        }

        $scheduledItem->update($validated);

        return redirect()->route('admin.scheduled-items.index')
            ->with('success', 'Scheduled item updated successfully.');
    }

    public function destroy(ScheduledItem $scheduledItem)
    {
        $scheduledItem->delete();

        return redirect()->route('admin.scheduled-items.index')
            ->with('success', 'Scheduled item deleted successfully.');
    }
}
