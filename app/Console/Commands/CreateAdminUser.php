<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AdminUserCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator user and send credentials via email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->ask('Enter the email address for the new administrator');

        if (User::whereEmail($email)->exists()) {
            $this->error("A user with email '{$email}' already exists.");

            return self::FAILURE;
        }

        $name = $this->ask('Enter the name for the new administrator');
        $temporaryPassword = Str::random(10);

        $user = User::create([
            'email' => $email,
            'name' => $name,
            'password' => Hash::make($temporaryPassword),
        ]);

        $user->assignRole('administrator');

        $user->notify(new AdminUserCreated($temporaryPassword));

        $this->info("Administrator '{$name}' ({$email}) has been created successfully!");
        $this->info("A temporary password has been sent to {$email}.");

        return self::SUCCESS;
    }
}
