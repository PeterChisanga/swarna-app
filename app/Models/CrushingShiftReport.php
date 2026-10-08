<?php

namespace App\Models;

use App\Models\Concerns\HasOperatingDate;
use App\Support\ShiftHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrushingShiftReport extends Model
{
    use HasOperatingDate;

    protected $table = 'crushing_shift_reports';
    protected $primaryKey = 'shift_report_id';

    protected $fillable = [
        'reading_date', 'reading_time', 'shift', 'moisture_sample_id', 'ore_type', 'ore_name_id',
        'crushing_start_time', 'trucks_crushed', 'crushing_stop_time', 'running_hours',
        'downtime_hours', 'downtime_reason', 'stockpile_pct', 'notes', 'report_stage',
        'closed_by', 'closed_at', 'entry_group_id', 'status', 'entered_by', 'reviewed_by',
        'reviewed_at', 'review_note', 'last_edited_by', 'last_edited_at',
    ];

    protected $casts = [
        'reading_date'   => 'date:Y-m-d',
        'trucks_crushed' => 'integer',
        'running_hours'  => 'decimal:2',
        'downtime_hours' => 'decimal:2',
        'stockpile_pct'  => 'decimal:2',
        'closed_at'      => 'datetime',
        'reviewed_at'    => 'datetime',
        'last_edited_at' => 'datetime',
    ];

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('report_stage', 'Open');
    }

    public function scopeClosed(Builder $q): Builder
    {
        return $q->where('report_stage', 'Closed');
    }

    public function isOpen(): bool
    {
        return $this->report_stage === 'Open';
    }

    public function moistureSample(): BelongsTo
    {
        return $this->belongsTo(CrushingMoistureSample::class, 'moisture_sample_id', 'moisture_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Moisture samples offered on the end-of-shift page: the same operating date,
     * Rejected samples excluded. A sample already linked stays in the list so an
     * Admin edit never silently loses the link.
     *
     * @return array<int, array{id:int,label:string}>
     */
    public function moistureOptions(): array
    {
        $samples = CrushingMoistureSample::notRejected()
            ->onOperatingDate($this->operating_date)
            ->where('stream_name', $this->ore_name_id)
            ->orderBy('reading_date')->orderBy('reading_time')
            ->get();

        if ($this->moisture_sample_id && ! $samples->contains('moisture_id', $this->moisture_sample_id)) {
            $linked = CrushingMoistureSample::find($this->moisture_sample_id);
            if ($linked) {
                $samples->push($linked);
            }
        }

        return $samples->map(fn ($s) => ['id' => $s->moisture_id, 'label' => $s->optionLabel()])->values()->all();
    }

    /** Read-only start-of-shift summary shown on the end-of-shift page. */
    public function startSummary(): array
    {
        return [
            ['Reading', $this->reading_date->format('d/m/Y') . ' ' . $this->time_short . ' · ' . $this->shift . ' shift'],
            ['Ore type', $this->ore_type],
            ['Ore Name / ID', $this->ore_name_id],
            ['Crushing start', $this->crushing_start_time ? substr($this->crushing_start_time, 0, 5) : 'Not reported'],
        ];
    }

    public function summaryText(): string
    {
        if ($this->isOpen()) {
            return 'Ore: ' . $this->ore_type . ' · ' . $this->ore_name_id . ' · awaiting end of shift';
        }

        $parts = [];
        if ($this->trucks_crushed !== null) {
            $parts[] = 'Trucks crushed: ' . $this->trucks_crushed . ' trucks';
        }
        if ($this->running_hours !== null) {
            $parts[] = 'Running hours: ' . ShiftHelper::num($this->running_hours, 'h');
        }
        if ($this->downtime_hours !== null && count($parts) < 2) {
            $parts[] = 'Downtime: ' . ShiftHelper::num($this->downtime_hours, 'h');
        }

        return implode(' · ', array_slice($parts, 0, 2)) ?: 'No reading';
    }

    /** Data for the "View" pop-up. NULL means "Not reported" - never zero. */
    public function detailPayload(): array
    {
        $time = fn ($t) => $t ? substr($t, 0, 5) : null;

        return [
            'title' => 'Crushing shift report details',
            'meta'  => [
                'Report'     => 'Shift report',
                'Date'       => $this->reading_date->format('d M Y'),
                'Time'       => $this->time_short,
                'Shift'      => $this->shift,
                'Stage'      => $this->report_stage,
                'Status'     => $this->status,
                'Entered by' => $this->enteredBy?->name ?? '-',
            ],
            'rows' => [
                ['group' => 'Start of shift'],
                ['label' => 'Crushing ore type', 'value' => $this->ore_type],
                ['label' => 'Crushing ore name / ID', 'value' => $this->ore_name_id],
                ['label' => 'Crushing start time', 'value' => $time($this->crushing_start_time)],
                ['group' => 'End of shift'],
                ['label' => 'Crushing moisture sample', 'value' => $this->moistureSample?->optionLabel()],
                ['label' => 'Crusher trucks crushed', 'value' => $this->trucks_crushed !== null ? $this->trucks_crushed . ' trucks' : null],
                ['label' => 'Crushing stop time', 'value' => $time($this->crushing_stop_time)],
                ['label' => 'Crusher running hours', 'value' => ShiftHelper::num($this->running_hours, 'h')],
                ['label' => 'Crusher downtime', 'value' => ShiftHelper::num($this->downtime_hours, 'h')],
                ['label' => 'Crusher downtime reason', 'value' => $this->downtime_reason],
                ['label' => 'Ore stockpile', 'value' => ShiftHelper::num($this->stockpile_pct, '%')],
                ['label' => 'Closed by', 'value' => $this->closedBy?->name],
            ],
            'notes'       => $this->notes,
            'review_note' => $this->review_note,
        ];
    }

    /** Values for the Admin edit pop-up (capture names). */
    public function editPayload(): array
    {
        $time = fn ($t) => $t ? substr($t, 0, 5) : '';

        return [
            'stage'  => $this->report_stage,
            'values' => [
                'crushing_reading_date'       => $this->reading_date->toDateString(),
                'crushing_reading_time'       => $this->time_short,
                'crushing_ore_type'           => $this->ore_type,
                'crushing_ore_name_id'        => $this->ore_name_id,
                'crushing_start_time'         => $time($this->crushing_start_time),
                'crushing_moisture_sample_id' => $this->moisture_sample_id ? (string) $this->moisture_sample_id : '',
                'crusher_trucks_crushed'      => $this->trucks_crushed !== null ? (string) $this->trucks_crushed : '',
                'crushing_stop_time'          => $time($this->crushing_stop_time),
                'crusher_running_hours'       => ShiftHelper::num($this->running_hours) ?? '',
                'crusher_downtime_hours'      => ShiftHelper::num($this->downtime_hours) ?? '',
                'crusher_downtime_reason'     => $this->downtime_reason ?? '',
                'ore_stockpile_pct'           => ShiftHelper::num($this->stockpile_pct) ?? '',
                'crushing_shift_notes'        => $this->notes ?? '',
            ],
            'moisture_options' => $this->isOpen() ? [] : $this->moistureOptions(),
        ];
    }
}
