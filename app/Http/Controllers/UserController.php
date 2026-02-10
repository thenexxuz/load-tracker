<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
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
}
