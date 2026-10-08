<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\CrushingShiftReport;
use App\Support\CrushingRules;
use App\Support\ShiftHelper;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRD 8.1.1 - one row per shift, submitted twice.
 *   open()  = start of shift  -> INSERT, stage Open
 *   close() = end of shift    -> UPDATE the same row, stage Closed
 */
class CrushingShiftReportController extends Controller
{
    /** Step 1 - Production, start of shift. */
    public function open(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->canEnterShift(), 403, 'Only Production can open a shift report.');

        try {
            $data = $request->validate(CrushingRules::start(), CrushingRules::messages());

            if (Auth::user()->isAdmin()) {
                // Admin may back-date, but never into the future (1 minute allowed for clock drift).
                $readingAt = Carbon::createFromFormat('Y-m-d H:i', $data['crushing_reading_date'] . ' ' . $data['crushing_reading_time']);

                if ($readingAt->gt(now()->addMinute())) {
                    throw ValidationException::withMessages([
                        'crushing_reading_time' => ['The reading date and time cannot be in the future.'],
                    ]);
                }
            } else {
                // Production always records the current date and time; posted values are ignored.
                $data['crushing_reading_date'] = now()->toDateString();
                $data['crushing_reading_time'] = now()->format('H:i');
            }

            $time   = $data['crushing_reading_time'];
            $shift  = ShiftHelper::shiftFor($time);
            $opDate = ShiftHelper::operatingDate($data['crushing_reading_date'], $time);

            // One OPEN report per date and shift. Once it is closed (e.g. material change) a new one may be opened.
            $existing = CrushingShiftReport::onOperatingDate($opDate)->where('shift', $shift)->open()->first();

            if ($existing) {
                return response()->json([
                    'message'            => 'A report is already open for this date and shift. Close it at the end of the shift (for example after a material change) before opening a new one.',
                    'existing_report_id' => $existing->shift_report_id,
                    'existing_stage'     => 'Open',
                ], 409);
            }

            $report = CrushingShiftReport::create(array_merge(
                CrushingRules::map($data, CrushingRules::SHIFT_MAP),
                [
                    'shift'        => $shift,
                    'report_stage' => 'Open',
                    'status'       => 'Pending',
                    'entered_by'   => Auth::id(),
                ]
            ));

            return response()->json([
                'message'        => 'Shift report opened · stage Open',
                'id'             => $report->shift_report_id,
                'operating_date' => $opDate,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Check the highlighted fields.', 'errors' => $e->errors()], 422);
        } catch (QueryException $e) {
            return $this->queryFailure($e, 'open shift report');
        } catch (\Throwable $e) {
            \Log::error('Open crushing shift report failed: ' . $e->getMessage());

            return response()->json(['message' => 'The report could not be saved. Try again.'], 500);
        }
    }

    /** Step 3 - Production, end of shift. Writes only the end-of-shift columns. */
    public function close(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->canEnterShift(), 403, 'Only Production can close a shift report.');

        try {
            $data = $request->validate(CrushingRules::end(), CrushingRules::messages());

            $report = DB::transaction(function () use ($id, $data) {
                // Lock the row: if two people submit at once, the second is told it is already closed.
                $report = CrushingShiftReport::lockForUpdate()->findOrFail($id);

                if (! $report->isOpen()) {
                    throw new \DomainException('This report is already closed. Changes can now only be made by an Admin edit.');
                }

                $sampleId = (int) $data['crushing_moisture_sample_id'];

                if ((int) $report->moisture_sample_id !== $sampleId) {
                    throw ValidationException::withMessages([
                        'crushing_moisture_sample_id' => [
                            'Select the moisture sample recorded for this shift report.',
                        ],
                    ]);
                }

                if (! collect($report->moistureOptions())->pluck('id')->contains($sampleId)) {
                    throw ValidationException::withMessages([
                        'crushing_moisture_sample_id' => ['Choose a moisture sample from this shift\'s operating date.'],
                    ]);
                }

                // The stop time cannot be in the future. A time earlier than the start means the next day (night shift).
                if (! empty($data['crushing_stop_time'])) {
                    $startAt = $report->reading_date->copy()->setTimeFromTimeString((string) $report->reading_time);
                    $stopAt  = $report->reading_date->copy()->setTimeFromTimeString($data['crushing_stop_time']);

                    if ($stopAt->lt($startAt)) {
                        $stopAt->addDay();
                    }

                    if ($stopAt->gt(now()->addMinute())) {
                        throw ValidationException::withMessages([
                            'crushing_stop_time' => ['The stop time cannot be in the future.'],
                        ]);
                    }
                }

                $report->fill(CrushingRules::map($data, CrushingRules::SHIFT_MAP));
                $report->fill([
                    'report_stage' => 'Closed',
                    'closed_by'    => Auth::id(),
                    'closed_at'    => now(),
                ]);
                $report->save();

                return $report;
            });

            return response()->json([
                'message'        => 'Shift report closed · Pending review',
                'id'             => $report->shift_report_id,
                'operating_date' => $report->operating_date,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Check the highlighted fields.', 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'That shift report no longer exists.'], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (QueryException $e) {
            return $this->queryFailure($e, 'close shift report');
        } catch (\Throwable $e) {
            \Log::error('Close crushing shift report failed: ' . $e->getMessage());

            return response()->json(['message' => 'The report could not be closed. Try again.'], 500);
        }
    }

    private function queryFailure(QueryException $e, string $action): JsonResponse
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) { // duplicate key
            return response()->json(['message' => 'A shift report for this date and shift already exists.'], 409);
        }

        \Log::error("Crushing {$action} query failed: " . $e->getMessage());

        return response()->json(['message' => 'The report could not be saved. Try again.'], 500);
    }
}
