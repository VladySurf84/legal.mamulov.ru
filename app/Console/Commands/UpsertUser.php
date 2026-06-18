<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UpsertUser extends Command
{
    protected $signature = 'user:upsert
        {email : User email}
        {--name= : User display name}
        {--password= : Optional password login}
        {--role=admin : User role}
        {--inactive : Mark user inactive}';

    protected $description = 'Create or update an application user.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email is invalid.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        $name = $this->option('name') ?: $email;

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = (string) $name;
        $user->role = (string) $this->option('role');
        $user->is_active = ! (bool) $this->option('inactive');

        if (is_string($password) && $password !== '') {
            $user->password = Hash::make($password);
        }

        if (! $user->exists && ! $user->password) {
            $user->password = null;
        }

        $user->save();

        $this->info(sprintf(
            '%s user %s (%s).',
            $user->wasRecentlyCreated ? 'Created' : 'Updated',
            $user->email,
            $user->is_active ? 'active' : 'inactive',
        ));

        return self::SUCCESS;
    }
}
