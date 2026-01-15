<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function edit(User $user)
    {
        return inertia('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            'allRoles' => Role::all()->pluck('name'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $user->syncRoles($request->roles ?? []);
        return redirect()->route('admin.users.index');
    }
}
