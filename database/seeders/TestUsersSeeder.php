<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /**
     * Crea (o actualiza) los usuarios de prueba que usa el frontend.
     *
     * Credenciales: ana@taskflow.test / password y beto@taskflow.test / password.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Ana Propietaria', 'email' => 'ana@taskflow.test'],
            ['name' => 'Beto Miembro', 'email' => 'beto@taskflow.test'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
