<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:25',
            'search' => 'nullable|string|max:500',
        ]);

        $query = User::with(['roles', 'carrier:id,name']);

        if ($validated['search'] ?? false) {
            $search = $validated['search'];
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }

        $users = $query
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles
                    ->pluck('name')
                    ->map(fn (string $roleName) => $roleName === 'carrier' && $user->carrier
                        ? "{$roleName} ({$user->carrier->name})"
                        : $roleName)
                    ->all(),
                'is_active' => $user->is_active,
                'deleted_at' => $user->deleted_at,
            ])
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $validated['search'] ?? null,
            ],
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            'allRoles' => Role::all()->pluck('name')->toArray(),
            'allCarriers' => Carrier::orderBy('name')->get(['id', 'name', 'short_code'])->toArray(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Validate input
        $request->validate([
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        // Critical security check: Prevent self-demotion from admin
        if (auth()->id() === $user->id) {
            $newRoles = collect($request->input('roles', []));

            // If user had 'administrator' role and it's being removed → block
            if ($user->hasRole('administrator') && ! $newRoles->contains('administrator')) {
                return back()
                    ->withInput()
                    ->withErrors(['roles' => 'You cannot remove your own administrator role.']);
            }
        }

        // Update carrier association
        if (in_array('carrier', $request->input('roles', []))) {
            $request->validate([
                'carrier_id' => 'required|exists:carriers,id',
            ]);
        } else {
            // If user is not a carrier, ensure carrier_id is null
            $request->merge(['carrier_id' => null]);

        }
        $user->carrier_id = $request->input('carrier_id');
        $user->save();

        // Sync roles
        $user->syncRoles($request->input('roles', []));

        // Redirect with success message (flash data)
        return redirect()->route('admin.users.index')
            ->with('success', 'User roles updated successfully.');
    }

    public function export()
    {
        $users = User::with('roles')->get();

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Email', 'Roles (comma-separated)', 'Created At']);

            foreach ($users as $user) {
                $roles = $user->roles->pluck('name')->join(', ');
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $roles,
                    $user->created_at,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $user = User::updateOrCreate(
                ['email' => $row[2]],
                ['name' => $row[1]]
            );

            // Preserve roles
            if (! empty($row[3])) {
                $roleNames = array_map('trim', explode(',', $row[3]));
                $user->syncRoles($roleNames);
            }
        }

        fclose($handle);

        return back()->with('success', 'Users imported successfully with roles preserved!');
    }

    public function disable(User $user)
    {
        // Prevent disabling yourself
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $user->update(['is_active' => false]);

        return back()->with('success', "{$user->name} has been disabled.");
    }

    public function enable(User $user)
    {
        // Prevent enabling if you don't have permission
        if (auth()->id() === $user->id && ! auth()->user()->hasRole('administrator')) {
            return back()->with('error', 'You do not have permission to enable this account.');
        }

        $user->update(['is_active' => true]);

        return back()->with('success', "{$user->name} has been enabled.");
    }

    public function delete(User $user)
    {
        // Prevent soft-deleting yourself
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', "{$user->name} has been deleted.");
    }

    public function restore(User $user)
    {
        $user->restore();

        return back()->with('success', "{$user->name} has been restored.");
    }
}
