<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = Activity::query()
            ->with(['causer:id,name'])
            ->when($request->search, function ($q, $search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('causer', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhere('subject_type', 'like', "%{$search}%");
            })
            ->when($request->model, fn ($q, $model) => $q->where('subject_type', 'like', "%{$model}%"))
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Audits/Index', [
            'logs' => $logs->through(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'user' => $log->causer?->name ?? 'System',
                'model' => class_basename($log->subject_type),
                'record_id' => $log->subject_id,
                'changes' => $log->properties['attributes'] ?? $log->properties['old'] ?? null,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]),
            'filters' => $request->only(['search', 'model']),
        ]);
    }
}
