<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Creates the first super-admin on a fresh install (production seeding
 * creates no users). Run after `db:seed` / `setup:permissions`.
 */
class CreateAdmin extends Command
{
    protected $signature = 'vems:create-admin
        {--name= : Full name}
        {--username= : Login username}
        {--email= : Email address}';

    protected $description = 'Create a super-admin user';

    public function handle(): int
    {
        if (! Role::where('name', 'super-admin')->exists()) {
            $this->error('The super-admin role does not exist yet. Run `php artisan setup:permissions` first.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name'),
            'username' => $this->option('username') ?: $this->ask('Username'),
            'email' => $this->option('email') ?: $this->ask('Email'),
            'password' => $this->secret('Password (min 8 characters)'),
        ];
        $data['password_confirmation'] = $this->secret('Confirm password');

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'user_type' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('super-admin');

        $this->info("Super-admin {$user->username} created.");

        return self::SUCCESS;
    }
}
