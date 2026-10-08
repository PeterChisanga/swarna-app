<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Validation rules and capture-name -> DB-column maps for Crushing (PRD 8.1).
 *
 * The forms post the PRD "data capture" attribute names (e.g. crusher_running_hours);
 * the DB uses the plain column names (running_hours).
 */
class CrushingRules
{
    public const SHIFT_MAP = [
        'crushing_reading_date'       => 'reading_date',
        'crushing_reading_time'       => 'reading_time',
        'crushing_ore_type'           => 'ore_type',
        'crushing_ore_name_id'        => 'ore_name_id',
        'crushing_start_time'         => 'crushing_start_time',
        'crushing_moisture_sample_id' => 'moisture_sample_id',
        'crusher_trucks_crushed'      => 'trucks_crushed',
        'crushing_stop_time'          => 'crushing_stop_time',
        'crusher_running_hours'       => 'running_hours',
        'crusher_downtime_hours'      => 'downtime_hours',
        'crusher_downtime_reason'     => 'downtime_reason',
        'ore_stockpile_pct'           => 'stockpile_pct',
        'crushing_shift_notes'        => 'notes',
    ];

    public const MOISTURE_MAP = [
        'moisture_reading_date'   => 'reading_date',
        'moisture_reading_time'   => 'reading_time',
        'moisture_sample_stream'  => 'stream_name',
        'crushed_ore_moisture_pct' => 'moisture_pct',
        'moisture_notes'          => 'notes',
    ];

    public static function map(array $data, array $map): array
    {
        $out = [];

        foreach ($map as $capture => $column) {
            if (array_key_exists($capture, $data)) {
                $out[$column] = $data[$capture];
            }
        }

        return $out;
    }

    /** Start-of-shift page (8.1.1). */
    public static function start(): array
    {
        return [
            'crushing_reading_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'crushing_reading_time' => ['required', 'date_format:H:i'],
            'crushing_ore_type'     => [
                'required', 'string', 'max:100',
                Rule::exists('crushing_ore_types', 'ore_type')->where('is_active', true),
            ],
            'crushing_ore_name_id'  => ['required', 'string', 'max:100'],
            'crushing_start_time'   => ['required', 'date_format:H:i'],
        ];
    }

    /** End-of-shift page (8.1.1). Moisture sample is mandatory here (PRD change). */
    public static function end(): array
    {
        return [
            'crushing_moisture_sample_id' => [
                'required', 'integer',
                Rule::exists('crushing_moisture_samples', 'moisture_id')
                    ->where(fn ($q) => $q->where('status', '!=', 'Rejected')),
            ],
            'crusher_trucks_crushed'  => ['required', 'integer', 'min:0', 'max:100000'],
            'crushing_stop_time'      => ['required', 'date_format:H:i'],
            'crusher_running_hours'   => ['nullable', 'numeric', 'min:0', 'max:24'],
            'crusher_downtime_hours'  => ['nullable', 'numeric', 'min:0', 'max:24'],
            'crusher_downtime_reason' => ['nullable', 'string', 'max:255'],
            'ore_stockpile_pct'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'crushing_shift_notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Moisture sample page (8.1.2). Date and stream are not posted: they come from the chosen shift report. */
    public static function moisture(): array
    {
        return [
            'crushing_shift_report_id' => ['required', 'integer', Rule::exists('crushing_shift_reports', 'shift_report_id')],
            'moisture_reading_time'    => ['required', 'date_format:H:i'],
            'crushed_ore_moisture_pct' => ['required', 'numeric', 'min:0', 'max:60'],
            'moisture_notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Admin edit of a moisture sample: the ore name stays as saved. */
    public static function moistureEdit(): array
    {
        return [
            'moisture_reading_date'    => ['required', 'date_format:Y-m-d'],
            'moisture_reading_time'    => ['required', 'date_format:H:i'],
            'crushed_ore_moisture_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'moisture_notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function messages(): array
    {
        return [
            'crushing_reading_date.before_or_equal' => 'The reading date cannot be in the future.',
            'crushing_reading_date.required'       => 'Select the reading date.',
            'crushing_reading_time.required'       => 'Select the actual reading time.',
            'crushing_ore_type.required'           => 'Crushing ore type is required.',
            'crushing_ore_type.exists'             => 'Choose an active ore type from the list.',
            'crushing_ore_name_id.required'        => 'Crushing ore name / ID is required.',
            'crushing_moisture_sample_id.required' => 'Select the moisture sample for this shift.',
            'crushing_moisture_sample_id.exists'   => 'That moisture sample is not available (it may have been rejected).',
            'crusher_trucks_crushed.required'      => 'Crusher trucks crushed is required.',
            'crusher_trucks_crushed.integer'       => 'Crusher trucks crushed must be a whole number.',
            'moisture_reading_date.required'       => 'Select the date the sample was taken.',
            'moisture_reading_time.required'       => 'Select the time the sample was taken.',
            'moisture_sample_stream.required'      => 'Crusher sample stream is required.',
            'moisture_sample_stream.exists'        => 'Choose an active stream from the list.',
            'crushed_ore_moisture_pct.required'    => 'Crushed ore moisture is required.',
            '*.numeric'                            => 'Enter a number only (for example 2.5).',
            '*.min'                                => 'The value is below the possible range. Check it.',
            '*.max'                                => 'The value is above the possible range. Check it.',
            '*.date_format'                        => 'Enter a valid date or time.',
        ];
    }
}
