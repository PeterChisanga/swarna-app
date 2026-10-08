<?php

namespace App\Support;

use App\Models\CrushingShiftReport;
use Carbon\Carbon;

/**
 * Live crusher state, from shift reports only.
 * "running" = a shift report is Open; the app has no signal from the machine itself.
 * An Open report older than plant.open_report_max_hours is flagged "overdue".
 */
class CrusherStatus
{
    public static function current(): array
    {
        $latest = fn ($q) => $q->orderByDesc('reading_date')->orderByDesc('reading_time');

        $open   = $latest(CrushingShiftReport::with('enteredBy')->notRejected()->open())->first();
        $closed = $latest(CrushingShiftReport::notRejected()->closed())->first();

        $maxOpenHours = (int) config('plant.open_report_max_hours', 14);

        $state      = 'none';
        $since      = null;
        $sinceLabel = null;
        $openedAt   = null;

        if ($open) {
            $openedAt = $open->reading_date->copy()->setTimeFromTimeString((string) $open->reading_time);
            $overdue  = $openedAt->diffInHours(now(), false) >= $maxOpenHours;

            $state      = $overdue ? 'overdue' : 'running';
            $since      = $openedAt->timestamp;
            $sinceLabel = $overdue ? 'End-of-shift report open for' : 'Shift running for';
        } elseif ($closed) {
            $state      = 'stopped';
            $since      = ($closed->closed_at ?? $closed->updated_at)?->timestamp;
            $sinceLabel = 'No shift open for';
        }

        return [
            'state'         => $state,
            'status'        => [
                'running' => 'Shift in progress',
                'overdue' => 'Shift report not closed',
                'stopped' => 'No shift open',
                'none'    => 'No shift reports yet',
            ][$state],
            'since'         => $since,
            'sinceLabel'    => $sinceLabel,
            'serverNow'     => now()->timestamp,
            'reportId'      => $open?->shift_report_id,
            'detail'        => $open ? $open->shift . ' shift · ' . $open->ore_type . ' · ' . $open->ore_name_id : null,
            'openedAt'      => $openedAt?->format('H:i'),
            'openedBy'      => $open?->enteredBy?->name,
            'moistureCount' => $open ? count($open->moistureOptions()) : null,
            'last'          => $closed ? [
                'trucks' => (int) $closed->trucks_crushed,
                'when'   => Carbon::parse($closed->operating_date)->format('d M Y') . ' · ' . $closed->shift . ' shift',
            ] : null,
        ];
    }
}
