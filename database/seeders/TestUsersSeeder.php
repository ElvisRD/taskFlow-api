<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Ana Propietaria', 'email' => 'ana@taskflow.test'],
            ['name' => 'Beto Miembro', 'email' => 'beto@taskflow.test'],
            ['name' => 'Carla Diseñadora', 'email' => 'carla@taskflow.test'],
            ['name' => 'Diego Desarrollador', 'email' => 'diego@taskflow.test'],
            ['name' => 'Elena Tester', 'email' => 'elena@taskflow.test'],
            ['name' => 'Fabio Producto', 'email' => 'fabio@taskflow.test'],
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
