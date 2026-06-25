<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed project users that are allowed to sign in through Google.
     */
    public function run(): void
    {
        $users = $this->users();
        $emails = array_column($users, 'email');

        User::query()
            ->whereNotIn('email', $emails)
            ->delete();

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => null,
                    'role' => $user['is_admin'] ? 'admin' : 'viewer',
                    'is_admin' => $user['is_admin'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{name: string, email: string, is_admin: bool}>
     */
    private function users(): array
    {
        return [
            ['name' => 'Vlady', 'email' => 'ecomicron@gmail.com', 'is_admin' => true],
            ['name' => 'Ivan', 'email' => 'vafgrt1996@gmail.com', 'is_admin' => false],
            ['name' => 'Abertos', 'email' => 'abertos16@gmail.com', 'is_admin' => false],
        ];
    }
}
