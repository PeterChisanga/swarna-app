<?php

namespace Database\Seeders;

use App\Models\OreType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sections must run before streams (streams.section_id).
        $this->call([
            SectionSeeder::class,
            StreamSeeder::class,
        ]);

        // Demo users - CHANGE THESE PASSWORDS before any real deployment.
        $users = [
            ['M.',      'Kaputula',   'production@smzl.local', 'Production'],
            ['Pundlik', 'Lab',        'lab@smzl.local',        'Lab'],
            ['Plant',   'Supervisor', 'admin@smzl.local',      'Admin'],
            ['A.',   'Peter',      'admin@gmail.com',       'Admin'],
            ['A.',   'Vasu',      'vasu@gmail.com',       'Admin']
        ];

        foreach ($users as [$first, $last, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $first,
                    'last_name'  => $last,
                    'role'       => $role,
                    'is_active'  => true,
                    'password'   => Hash::make('password'),
                ]
            );
        }

        foreach (['Sulphide', 'Oxide', 'Mixed oxide'] as $oreType) {
            OreType::firstOrCreate(['ore_type' => $oreType]);
        }
    }
}
