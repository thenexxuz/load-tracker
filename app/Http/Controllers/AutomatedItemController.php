<?php

namespace App\Http\Controllers;

use App\Models\AutomatedItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AutomatedItemController extends Controller
{
    public function index(): Response
    {
        $automatedItems = AutomatedItem::query()
            ->latest()
            ->paginate(15)
            ->through(function (AutomatedItem $item): array {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'monitorable_type' => $item->monitorable_type,
                    'monitorable_label' => AutomatedItem::labelForClass($item->monitorable_type),
                    'monitored_fields' => $item->monitored_fields,
                    'role_name' => $item->role_name,
                    'is_active' => $item->is_active,
                    'created_at' => $item->created_at,
                ];
            });

        return Inertia::render('Admin/AutomatedItems/Index', [
            'automatedItems' => $automatedItems,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AutomatedItems/Create', [
            'monitorableOptions' => $this->monitorableOptions(),
            'monitorableFields' => AutomatedItem::monitorableFieldsByKey(),
            'roles' => Role::query()->orderBy('name')->get(['name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        AutomatedItem::create($validated);

        return redirect()->route('admin.automated-items.index')
            ->with('success', 'Automated item created successfully.');
    }

    public function edit(AutomatedItem $automatedItem): Response
    {
        return Inertia::render('Admin/AutomatedItems/Edit', [
            'automatedItem' => [
                'id' => $automatedItem->id,
                'name' => $automatedItem->name,
                'monitorable_key' => AutomatedItem::keyForClass($automatedItem->monitorable_type),
                'monitored_fields' => $automatedItem->monitored_fields,
                'role_name' => $automatedItem->role_name,
                'is_active' => $automatedItem->is_active,
            ],
            'monitorableOptions' => $this->monitorableOptions(),
            'monitorableFields' => AutomatedItem::monitorableFieldsByKey(),
            'roles' => Role::query()->orderBy('name')->get(['name']),
        ]);
    }

    public function update(Request $request, AutomatedItem $automatedItem)
    {
        $validated = $this->validatePayload($request);

        $automatedItem->update($validated);

        return redirect()->route('admin.automated-items.index')
            ->with('success', 'Automated item updated successfully.');
    }

    public function destroy(AutomatedItem $automatedItem)
    {
        $automatedItem->delete();

        return redirect()->route('admin.automated-items.index')
            ->with('success', 'Automated item deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $monitorableKeys = array_keys(AutomatedItem::monitorableMap());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'monitorable_type' => ['required', 'string', Rule::in($monitorableKeys)],
            'monitored_fields' => ['required', 'array', 'min:1'],
            'monitored_fields.*' => ['required', 'string'],
            'role_name' => ['required', 'string', Rule::exists('roles', 'name')],
            'is_active' => ['boolean'],
        ]);

        $modelClass = AutomatedItem::classForKey($validated['monitorable_type']);
        $allowedFields = AutomatedItem::monitorableFieldsByKey()[$validated['monitorable_type']] ?? [];
        $selectedFields = array_values(array_unique(array_intersect($validated['monitored_fields'], $allowedFields)));

        if ($modelClass === null || $selectedFields === []) {
            throw ValidationException::withMessages([
                'monitored_fields' => 'Please select one or more valid properties for the selected model.',
            ]);
        }

        return [
            'name' => $validated['name'],
            'monitorable_type' => $modelClass,
            'monitored_fields' => $selectedFields,
            'role_name' => $validated['role_name'],
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    /**
     * @return array<int, array{key:string,label:string}>
     */
    private function monitorableOptions(): array
    {
        return collect(AutomatedItem::monitorableMap())
            ->keys()
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => AutomatedItem::monitorableLabels()[$key] ?? ucfirst($key),
            ])
            ->values()
            ->all();
    }
}
