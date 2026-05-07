<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario Admin
        User::create([
            'id' => '0000000001',
            'nombres' => 'Administrador',
            'apa' => 'Sistema',
            'ama' => 'Principal',
            'email' => 'admin@sistema.com',
            'password' => Hash::make('password123'),
            'perfil_id' => 1,
            'activo' => true,
        ]);

        // Usuario Profesor
        User::create([
            'id' => '0000000002',
            'nombres' => 'Juan',
            'apa' => 'García',
            'ama' => 'López',
            'email' => 'juan.garcia@sistema.com',
            'password' => Hash::make('password123'),
            'perfil_id' => 2,
            'activo' => true,
        ]);

        // Usuario Estudiante
        User::create([
            'id' => '0000000003',
            'nombres' => 'María',
            'apa' => 'Rodríguez',
            'ama' => 'Martínez',
            'email' => 'maria.rodriguez@sistema.com',
            'password' => Hash::make('password123'),
            'perfil_id' => 3,
            'activo' => true,
        ]);

        // Más usuarios de prueba
        User::create([
            'id' => '0000000004',
            'nombres' => 'Carlos',
            'apa' => 'Hernández',
            'ama' => 'Flores',
            'email' => 'carlos.hernandez@sistema.com',
            'password' => Hash::make('password123'),
            'perfil_id' => 3,
            'activo' => true,
        ]);
    }
}

