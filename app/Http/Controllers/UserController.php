<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get(); // Load roles if using Spatie

        return Inertia::render('Admin/Users/Index', [  // Adjust page/component name as needed
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'), // For display
                    // Add more fields as needed
                    'edit_url' => route('admin.users.edit', $user->id),
                ];
            }),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            'allRoles' => Role::all()->pluck('name')->toArray(),
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

        // If passed the check → sync roles
        $user->syncRoles($request->input('roles', []));

        return redirect()->route('admin.users.index')
            ->with('success', 'User roles updated successfully.');
    }
}
