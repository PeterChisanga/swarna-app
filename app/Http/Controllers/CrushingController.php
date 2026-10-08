<?php

namespace App\Http\Controllers;

use App\Models\CrushingMoistureSample;
use App\Models\CrushingShiftReport;
use App\Models\OreType;
use App\Models\Stream;
use App\Support\ShiftHelper;
use App\Support\CrusherStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CrushingController extends Controller
{
    /** Operating date + shift from the topbar filters (query string). */
    public static function filters(Request $request): array
    {
        $date = (string) $request->query('date', '');

        try {
            $date = Carbon::createFromFormat('Y-m-d', $date)->toDateString();
        } catch (\Throwable $e) {
            $date = ShiftHelper::currentOperatingDate();
        }

        $shift = $request->query('shift');
        $shift = in_array($shift, ['Day', 'Night'], true) ? $shift : 'all';

        return [$date, $shift];
    }

    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            [$date, $shift] = self::filters($request);

            // Records shown by the operating date / shift filters.
            $reports = CrushingShiftReport::notRejected()
                ->where(function ($query) {
                    $query->whereNull('moisture_sample_id')
                        ->orWhereHas('moistureSample', function ($sampleQuery) {
                            $sampleQuery->where('status', 'Rejected');
                        });
                })
                ->orderByDesc('reading_date')
                ->orderByDesc('reading_time')
                ->get();

            $samples = CrushingMoistureSample::with('enteredBy')->notRejected()->inView($date, $shift)
                ->orderByDesc('reading_date')->orderByDesc('reading_time')->get();

            $rows = $reports->map(fn ($m) => ['kind' => 'shift', 'label' => 'Shift report', 'team' => 'Production', 'm' => $m])
                ->concat($samples->map(fn ($m) => ['kind' => 'moisture', 'label' => 'Moisture sample', 'team' => 'Lab', 'm' => $m]))
                ->sortByDesc(fn ($r) => $r['m']->sort_key)
                ->take(12)->values();

            // Section KPIs (NULL is shown as "No reading", never zero).
            $latestReport = $reports->first();
            $withTrucks = $reports->first(fn ($r) => $r->trucks_crushed !== null);
            $withDowntime = $reports->first(fn ($r) => $r->downtime_hours !== null);
            $latestMoisture = $samples->first();

            $kpis = [
                'status' => [
                    'value' => $latestReport ? ($latestReport->isOpen() ? 'Shift in progress' : 'Shift closed') : 'No report',
                    'sub'   => $latestReport ? 'Opened ' . $latestReport->time_short . ' · ' . $latestReport->shift . ' shift' : 'Nothing opened for this filter',
                ],
                'trucks' => [
                    'value' => $withTrucks ? (string) $withTrucks->trucks_crushed : 'No reading',
                    'sub'   => $withTrucks ? 'Production · ' . $withTrucks->shift . ' shift' : 'Entered at end of shift',
                ],
                'downtime' => [
                    'value' => $withDowntime ? ShiftHelper::num($withDowntime->downtime_hours, 'h') : 'No reading',
                    'sub'   => $withDowntime ? ($withDowntime->downtime_reason ?: 'Production') : 'Entered at end of shift',
                ],
                'moisture' => [
                    'value' => $latestMoisture ? ShiftHelper::num($latestMoisture->moisture_pct, '%') : 'No reading',
                    'sub'   => $latestMoisture ? 'Lab · ' . $latestMoisture->time_short : 'Lab sample not captured yet',
                ],
            ];

            // Trend: trucks crushed over the last 10 closed shifts.
            $trendRows = CrushingShiftReport::notRejected()->closed()->whereNotNull('trucks_crushed')
                ->orderByDesc('reading_date')->orderByDesc('reading_time')->limit(10)->get()->reverse()->values();
            $max = max(1, (int) $trendRows->max('trucks_crushed'));
            $trend = $trendRows->map(fn ($r) => [
                'height' => max(8, (int) round($r->trucks_crushed / $max * 100)),
                'title'  => $r->reading_date->format('d M') . ' ' . $r->shift . ': ' . $r->trucks_crushed . ' trucks',
            ]);

            // End-of-shift page: every report still Open, each with its own moisture list.
            $openReports = CrushingShiftReport::open()->orderByDesc('reading_date')->orderByDesc('reading_time')->get()
                ->map(fn ($r) => [
                    'id'               => $r->shift_report_id,
                    'label'            => $r->reading_date->format('d/m/Y') . ' ' . $r->time_short . ' · ' . $r->shift . ' shift · ' . $r->ore_type,
                    'summary'          => $r->startSummary(),
                    'moisture_options' => $r->moistureOptions(),
                    'start_date'       => $r->reading_date->toDateString(),
                    'start_time'       => $r->time_short,
                ])->values();

            // Lab: shifts a sample can be captured against (open, or no moisture assigned yet).
            $moistureShifts = CrushingShiftReport::notRejected()
                ->where(function ($q) {
                    $q->where('report_stage', 'Open')
                    ->orWhereNull('moisture_sample_id')
                    ->orWhereHas('moistureSample', fn ($s) => $s->where('status', 'Rejected'));
                })
                ->orderByDesc('reading_date')->orderByDesc('reading_time')
                ->limit(30)->get()
                ->map(fn ($r) => [
                    'id'      => $r->shift_report_id,
                    'label'   => $r->reading_date->format('d/m/Y') . ' ' . $r->time_short . ' · ' . $r->shift . ' shift · ' . $r->ore_name_id . ' · ' . $r->report_stage,
                    'ore'     => $r->ore_name_id,
                    'op_date' => $r->operating_date,
                    'shift'   => $r->shift,
                    'stage'   => $r->report_stage,
                ])->values();

            return view('crushing.index', [
                'date'         => $date,
                'shift'        => $shift,
                'rows'         => $rows,
                'kpis'         => $kpis,
                'trend'        => $trend,
                'openReports'  => $openReports,
                'live' => CrusherStatus::current(),
                'oreTypes'     => OreType::active()->orderBy('ore_type')->pluck('ore_type', 'ore_type')->all(),
                'moistureShifts' => $moistureShifts,
                // 'streams'      => Stream::active()->forSection('Crushing')->orderBy('stream_name')->pluck('stream_name', 'stream_name')->all(),
                'canShift'     => $user->canEnterShift(),
                'canMoisture'  => $user->canEnterMoisture(),
                'canBackdate' => $user->isAdmin(),
                'defaultDate'  => now()->toDateString(),
                'defaultTime'  => now()->format('H:i'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Crushing page failed: ' . $e->getMessage());

            abort(500, 'The Crushing page could not be loaded.');
        }
    }

    /** Polled by the live banner every 30 s. */
    public function status(): JsonResponse
    {
        return response()->json(CrusherStatus::current());
    }
}
