<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LabelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!User::where('email', 'label@melodic.com')->exists()) {

            User::create([
                'username'     => 'Melodic Records',
                'email'        => 'label@melodic.com',
                'password'     => Hash::make('password'),
                'role'         => 'label',
                'is_suscribed' => true,
                'label'        => null,
            ]);

            $this->command->info('¡Label de prueba creada exitosamente!');
            $this->command->info('Email: label@melodic.com');
            $this->command->info('Password: password');

        } else {
            $this->command->warn('La Label de prueba ya existe.');
        }
    }
}
