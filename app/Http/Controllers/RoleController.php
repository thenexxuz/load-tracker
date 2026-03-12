<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return inertia('Admin/Roles/Index', [
            'roles' => Role::with('permissions')->get(),
            'permissions' => Permission::all()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        return response()->json([
            'role' => $role->load('permissions'),
            'permissions' => Permission::all()->pluck('name')->toArray(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,'.$role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.show', $role->id)
            ->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Cannot delete role in use by users.');
        }
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted.');
    }

    public function export()
    {
        $roles = Role::all();

        $callback = function () use ($roles) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Guard Name']);

            foreach ($roles as $role) {
                fputcsv($file, [$role->id, $role->name, $role->guard_name]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="roles-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getPathname(), 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            Role::updateOrCreate(
                ['name' => $row[1]],
                ['guard_name' => $row[2] ?? 'web']
            );
        }
        fclose($handle);

        return back()->with('success', 'Roles imported successfully!');
    }
}
