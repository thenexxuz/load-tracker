<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // List of roles to create (using firstOrCreate to avoid duplicates)
        $roles = [
            'administrator',
            'supervisor',
            'specialist',
            'truckload',
            'data-entry',
            'carrier',
            'recycler',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web'] // default guard; change to 'api' if using API tokens
            );
        }

        $this->command->info('Roles seeded successfully: '.implode(', ', $roles));
    }
}
