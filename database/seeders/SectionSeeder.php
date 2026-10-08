<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $sections = [
            'Crushing',
            'Milling',
            'Leach',
            'SX',
            'EW',
        ];

        foreach ($sections as $section) {
            DB::table('sections')->updateOrInsert(
                [
                    'section_name' => $section,
                ],
                [
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
