<?php

namespace App\Http\Controllers;

use App\Models\CrushingShiftReport;
use App\Support\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $latest = fn ($q) => $q->orderByDesc('reading_date')->orderByDesc('reading_time');

            // Rejected reports are ignored; Pending and Approved both count.
            $openReport = $latest(CrushingShiftReport::notRejected()->open())->first();
            $lastClosed = $latest(CrushingShiftReport::notRejected()->closed())->first();

            $maxOpenHours = (int) config('plant.open_report_max_hours', 14);

            $state      = 'none';
            $since      = null;
            $sinceLabel = null;

            if ($openReport) {
                $openedAt = $openReport->reading_date->copy()
                    ->setTimeFromTimeString((string) $openReport->reading_time);
                $overdue = $openedAt->diffInHours(now(), false) >= $maxOpenHours;

                $state      = $overdue ? 'overdue' : 'running';
                $since      = $openedAt->timestamp;
                $sinceLabel = $overdue ? 'End-of-shift report open for' : 'Shift running for';
            } elseif ($lastClosed) {
                $state      = 'stopped';
                $since      = ($lastClosed->closed_at ?? $lastClosed->updated_at)?->timestamp;
                $sinceLabel = 'No shift open for';
            }

            $status = [
                'running' => 'Shift in progress',
                'overdue' => 'Shift report not closed',
                'stopped' => 'No shift open',
                'none'    => 'No shift reports yet',
            ][$state];

            $closed = fn () => CrushingShiftReport::notRejected()->closed();
            $focus  = $openReport ?? $lastClosed;

            $crusher = [
                'state'      => $state,
                'status'     => $status,
                'since'      => $since,
                'sinceLabel' => $sinceLabel,
                'serverNow'  => now()->timestamp,
                'detail'     => $openReport
                    ? $openReport->shift . ' shift · ' . $openReport->ore_type . ' · ' . $openReport->ore_name_id
                    : null,
                'last'       => $lastClosed ? [
                    'trucks' => (int) $lastClosed->trucks_crushed,
                    'when'   => Carbon::parse($lastClosed->operating_date)->format('d M Y') . ' · ' . $lastClosed->shift . ' shift',
                ] : null,
                'month'      => $lastClosed
                    ? (int) $closed()->where('reading_date', '>=', now()->startOfMonth()->toDateString())->sum('trucks_crushed')
                    : null,
                'total'      => $lastClosed ? (int) $closed()->sum('trucks_crushed') : null,
                'firstDate'  => $lastClosed
                    ? Carbon::parse($closed()->min('reading_date'))->format('d M Y')
                    : null,
                'url'        => route('crushing.index', $focus ? ['date' => $focus->operating_date] : []),
            ];

            $recent = $latest(
                CrushingShiftReport::notRejected()
                    ->closed()
                    ->with('moistureSample')
                )->limit(5)->get();

            return view('dashboard', [
                'crusher'      => $crusher,
                'recent'       => $recent,
                'pendingCount' => Review::pendingCount(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Dashboard failed: ' . $e->getMessage());

            abort(500, 'The overview could not be loaded.');
        }
    }
}
