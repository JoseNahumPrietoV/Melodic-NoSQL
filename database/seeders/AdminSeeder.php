<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!User::where('email', 'admin@melodic.com')->exists()) {

            User::create([
                'username'     => 'Melodic Admin',
                'email'        => 'admin@melodic.com',
                'password'     => Hash::make('admin123'),
                'role'         => 'admin',
                'is_suscribed' => true,
                'label'        => null,
            ]);

            $this->command->info('¡Usuario Administrador creado exitosamente!');
            $this->command->info('Email: admin@melodic.com');
            $this->command->info('Password: admin123');

        } else {
            $this->command->warn('El usuario Administrador ya existe.');
        }
    }
}
