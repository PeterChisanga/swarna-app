<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StreamSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $streams = [
            'Crushing' => [
                'Crushed Ore',
            ],

            'Milling' => [
                'Mill Feed',
                'BM1',
                'Classifier Overflow',
            ],

            'Leach' => [
                'Reactor 1',
                'Reactor 2',
                'Reactor 3',
                'Leach Feed Point',
                'Raffinate to Leach',
                'Reactor 2 Overflow',
                'Reactor 3 Overflow',
                'PLS',
            ],

            'SX' => [
                'PLS',
                'Raffinate',
                'Advance',
                'Spent',
            ],

            'EW' => [
                'EW',
                'Cathode Harvest',
            ],
        ];

        foreach ($streams as $sectionName => $sectionStreams) {
            $sectionId = DB::table('sections')
                ->where('section_name', $sectionName)
                ->value('section_id');

            foreach ($sectionStreams as $streamName) {
                DB::table('streams')->updateOrInsert(
                    [
                        'section_id' => $sectionId,
                        'stream_name' => $streamName,
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
}
