<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EicSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'eic@peerreview.edu'],
            [
                'full_name' => 'Editor in Chief',
                'password' => Hash::make('EicPassword123'),
                'role' => 'eic',
                'institution' => 'University of Salahaddin',
            ]
        );

        $this->command->info('EIC account seeded: eic@peerreview.edu / EicPassword123');
    }
}
