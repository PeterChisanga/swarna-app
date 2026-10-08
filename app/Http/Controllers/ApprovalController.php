<?php

namespace App\Http\Controllers;

use App\Models\CrushingMoistureSample;
use App\Models\CrushingShiftReport;
use App\Models\OreType;
use App\Models\Stream;
use App\Support\CrushingRules;
use App\Support\ShiftHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin only: approve / reject / edit, plus the Admin-maintained dropdown lists.
 */
class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        try {
            $type   = in_array($request->query('type'), ['shift', 'moisture'], true) ? $request->query('type') : 'all';
            $status = in_array($request->query('status'), ['Pending', 'Approved', 'Rejected'], true) ? $request->query('status') : 'all';
            $date   = '';

            try {
                $date = Carbon::createFromFormat('Y-m-d', (string) $request->query('date'))->toDateString();
            } catch (\Throwable $e) {
                $date = '';
            }

            $filter = fn ($q) => $q
                ->when($status !== 'all', fn ($x) => $x->where('status', $status))
                ->when($date !== '', fn ($x) => $x->whereDate('reading_date', $date));

            $rows = collect();

            if ($type !== 'moisture') {
                $rows = $rows->concat($filter(CrushingShiftReport::with('enteredBy'))->get()->map(fn ($m) => [
                    'kind'      => 'shift',
                    'id'        => $m->shift_report_id,
                    'table'     => 'crushing_shift_reports',
                    'team'      => 'Production',
                    'canReview' => ! $m->isOpen(),
                    'm'         => $m,
                ]));
            }

            if ($type !== 'shift') {
                $rows = $rows->concat($filter(CrushingMoistureSample::with('enteredBy'))->get()->map(fn ($m) => [
                    'kind'      => 'moisture',
                    'id'        => $m->moisture_id,
                    'table'     => 'crushing_moisture_samples',
                    'team'      => 'Lab',
                    'canReview' => true,
                    'm'         => $m,
                ]));
            }

            $rows = $rows->sortByDesc(fn ($r) => $r['m']->sort_key)->take(200)->values();

            return view('approvals.index', [
                'rows'     => $rows,
                'type'     => $type,
                'status'   => $status,
                'dateFilter' => $date,
                'oreTypes' => OreType::orderBy('ore_type')->get(),
                'streams'  => Stream::orderBy('stream_name')->get(),
                'lists'    => [
                    'oreTypes' => OreType::active()->orderBy('ore_type')->pluck('ore_type')->all(),
                    'streams'  => Stream::active()->orderBy('stream_name')->pluck('stream_name')->all(),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Approvals page failed: ' . $e->getMessage());

            abort(500, 'The approvals page could not be loaded.');
        }
    }

    public function approve(string $kind, int $id): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        try {
            $model = $this->resolve($kind, $id);

            if ($blocked = $this->reviewBlocked($kind, $model)) {
                return response()->json(['message' => $blocked], 409);
            }

            $model->update([
                'status'      => 'Approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => null,
            ]);

            return response()->json(['message' => 'Record marked Approved']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'That record no longer exists.'], 404);
        } catch (\Throwable $e) {
            \Log::error('Approve failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not approve the record. Try again.'], 500);
        }
    }

    public function reject(Request $request, string $kind, int $id): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        try {
            $data = $request->validate(
                ['review_note' => ['required', 'string', 'max:255']],
                ['review_note.required' => 'Enter a reason for rejecting this record.']
            );

            $model = $this->resolve($kind, $id);

            if ($blocked = $this->reviewBlocked($kind, $model)) {
                return response()->json(['message' => $blocked], 409);
            }

            $model->update([
                'status'      => 'Rejected',
                'review_note' => $data['review_note'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return response()->json(['message' => 'Record rejected with a reason']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'That record no longer exists.'], 404);
        } catch (\Throwable $e) {
            \Log::error('Reject failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not reject the record. Try again.'], 500);
        }
    }

    /** Admin edit: status stays as it is; last_edited_by / last_edited_at are recorded. */
    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        try {
            $model = $this->resolve($kind, $id);

            if ($kind === 'moisture') {
                $data = $request->validate(CrushingRules::moistureEdit(), CrushingRules::messages());
                $cols = CrushingRules::map($data, CrushingRules::MOISTURE_MAP);   // date, time, moisture_pct, notes

                $duplicate = CrushingMoistureSample::whereDate('reading_date', $cols['reading_date'])
                    ->where('reading_time', $cols['reading_time'] . ':00')
                    ->where('stream_name', $model->stream_name)
                    ->where('moisture_id', '!=', $id)
                    ->exists();

                if ($duplicate) {
                    return response()->json(['message' => 'Another sample already has this date, time and ore.'], 409);
                }
            } else {
                // End-of-shift fields are only editable once the report is Closed.
                $rules = CrushingRules::start();
                if (! $model->isOpen()) {
                    $rules += CrushingRules::end();
                }

                $data = $request->validate($rules, CrushingRules::messages());
                $cols = CrushingRules::map($data, CrushingRules::SHIFT_MAP);
                $cols['shift'] = ShiftHelper::shiftFor($cols['reading_time']);
                $opDate = ShiftHelper::operatingDate($cols['reading_date'], $cols['reading_time']);

                $clash = CrushingShiftReport::onOperatingDate($opDate)
                    ->where('shift', $cols['shift'])
                    ->where('shift_report_id', '!=', $id)
                    ->exists();

                if ($clash) {
                    return response()->json(['message' => 'Another report already exists for that date and shift.'], 409);
                }
            }

            $cols['last_edited_by'] = Auth::id();
            $cols['last_edited_at'] = now();

            $model->update($cols);

            return response()->json(['message' => 'Edit saved; status unchanged']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'That record no longer exists.'], 404);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['message' => 'Another record already uses that date and shift.'], 409);
            }

            \Log::error('Admin edit query failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not save the edit. Try again.'], 500);
        } catch (\Throwable $e) {
            \Log::error('Admin edit failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not save the edit. Try again.'], 500);
        }
    }

    /** Admin-maintained dropdown lists: ore types (shift report) and streams (moisture sample). */
    public function storeMaster(Request $request, string $type): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        try {
            [$modelClass, $table, $column] = $this->masterTarget($type);

            $data = $request->validate(
                ['name' => ['required', 'string', 'max:100', "unique:{$table},{$column}"]],
                [
                    'name.required' => 'Enter a name.',
                    'name.unique'   => 'That name already exists in the list.',
                ]
            );

            $modelClass::create([$column => trim($data['name'])]);

            return response()->json(['message' => 'Added to the dropdown list']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            \Log::error('Add dropdown value failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not add the value. Try again.'], 500);
        }
    }

    public function toggleMaster(string $type, int $id): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        try {
            [$modelClass] = $this->masterTarget($type);

            $row = $modelClass::findOrFail($id);
            $row->is_active = ! $row->is_active;
            $row->save();

            return response()->json(['message' => 'Dropdown name status updated']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'That list item no longer exists.'], 404);
        } catch (\Throwable $e) {
            \Log::error('Toggle dropdown value failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not update the list. Try again.'], 500);
        }
    }

    private function resolve(string $kind, int $id)
    {
        return $kind === 'moisture'
            ? CrushingMoistureSample::findOrFail($id)
            : CrushingShiftReport::findOrFail($id);
    }

    /** Approve / Reject are offered once a shift report is Closed, and only while Pending. */
    private function reviewBlocked(string $kind, $model): ?string
    {
        if ($kind === 'shift' && $model->isOpen()) {
            return 'This shift report is still Open (shift in progress). Approve or reject it once it is Closed.';
        }

        if ($model->status !== 'Pending') {
            return 'This record has already been reviewed.';
        }

        return null;
    }

    private function masterTarget(string $type): array
    {
        return match ($type) {
            'ore-types' => [OreType::class, 'crushing_ore_types', 'ore_type'],
            'streams'   => [Stream::class, 'streams', 'stream_name'],
            default     => abort(404),
        };
    }
}
