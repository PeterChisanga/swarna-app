<?php

namespace App\Http\Controllers;

use App\Models\CrushingMoistureSample;
use App\Models\CrushingShiftReport;
use App\Support\CrushingRules;
use App\Support\ShiftHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRD 8.1.2 - Lab moisture sample, always captured against a shift report.
 * The form posts the chosen shift; the date, shift and stream (ore name) come from that report.
 */
class CrushingMoistureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->canEnterMoisture(), 403, 'Only Lab can capture a moisture sample.');

        try {
            $data = $request->validate(CrushingRules::moisture(), CrushingRules::messages());

            [$sample, $report] = DB::transaction(function () use ($data) {
                $report = CrushingShiftReport::lockForUpdate()->findOrFail((int) $data['crushing_shift_report_id']);

                // Allow one sample per report, with replacement if it was rejected.
                $eligible = $report->status !== 'Rejected'
                    && (
                        $report->moisture_sample_id === null
                        || $report->moistureSample?->status === 'Rejected'
                    );

                if (! $eligible) {
                    throw ValidationException::withMessages([
                        'crushing_shift_report_id' => [
                            'This shift already has a moisture sample.',
                        ],
                    ]);
                }

                $time = $data['moisture_reading_time'];
                $date = ShiftHelper::sampleDate($report->operating_date, $time);

                if (Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time)->gt(now()->addMinute())) {
                    throw ValidationException::withMessages([
                        'moisture_reading_time' => ['The sample time cannot be in the future.'],
                    ]);
                }

                $duplicate = CrushingMoistureSample::whereDate('reading_date', $date)
                    ->where('reading_time', $time . ':00')
                    ->where('stream_name', $report->ore_name_id)
                    ->exists();

                if ($duplicate) {
                    throw new \DomainException('A sample for this ore at this date and time already exists. Choose another time or ask Admin to edit it.');
                }

                $sample = CrushingMoistureSample::create([
                    'reading_date' => $date,
                    'reading_time' => $time,
                    'shift'        => $report->shift,
                    'stream_name'  => $report->ore_name_id,
                    'moisture_pct' => $data['crushed_ore_moisture_pct'],
                    'notes'        => $data['moisture_notes'] ?? null,
                    'status'       => 'Pending',
                    'entered_by'   => Auth::id(),
                ]);

                // Link immediately, whether the report is Open or Closed. (Currently we only allow 1 moisture sample per shift)
                $report->moisture_sample_id = $sample->moisture_id;
                $report->save();

                return [$sample, $report];
            });

            return response()->json([
                'message'        => 'Moisture sample saved · Pending review',
                'id'             => $sample->moisture_id,
                'operating_date' => $report->operating_date,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Check the highlighted fields.', 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'That shift report no longer exists.'], 404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['message' => 'A sample for this ore at this date and time already exists.'], 409);
            }

            \Log::error('Crushing moisture sample query failed: ' . $e->getMessage());

            return response()->json(['message' => 'The sample could not be saved. Try again.'], 500);
        } catch (\Throwable $e) {
            \Log::error('Crushing moisture sample failed: ' . $e->getMessage());

            return response()->json(['message' => 'The sample could not be saved. Try again.'], 500);
        }
    }
}
