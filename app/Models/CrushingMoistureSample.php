<?php

namespace App\Models;

use App\Models\Concerns\HasOperatingDate;
use App\Support\ShiftHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrushingMoistureSample extends Model
{
    use HasOperatingDate;

    protected $table = 'crushing_moisture_samples';
    protected $primaryKey = 'moisture_id';

    protected $fillable = [
        'reading_date', 'reading_time', 'shift', 'stream_name', 'moisture_pct', 'notes',
        'entry_group_id', 'status', 'entered_by', 'reviewed_by', 'reviewed_at', 'review_note',
        'last_edited_by', 'last_edited_at',
    ];

    protected $casts = [
        'reading_date'   => 'date:Y-m-d',
        'moisture_pct'   => 'decimal:4',
        'reviewed_at'    => 'datetime',
        'last_edited_at' => 'datetime',
    ];

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function shiftReports(): HasMany
    {
        return $this->hasMany(CrushingShiftReport::class, 'moisture_sample_id', 'moisture_id');
    }

    /** "14/09/2026 07:30 · Crushed Ore · 7.2 %" - the label used in the shift report's Moisture sample list. */
    public function optionLabel(): string
    {
        return $this->reading_date->format('d/m/Y') . ' ' . $this->time_short
            . ' · ' . $this->stream_name . ' · ' . ShiftHelper::num($this->moisture_pct, '%');
    }

    public function summaryText(): string
    {
        return $this->stream_name . ': ' . ShiftHelper::num($this->moisture_pct, '%');
    }

    /** Data for the "View" pop-up. */
    public function detailPayload(): array
    {
        return [
            'title' => 'Moisture sample details',
            'meta'  => [
                'Report'     => 'Moisture sample',
                'Date'       => $this->reading_date->format('d M Y'),
                'Time'       => $this->time_short,
                'Shift'      => $this->shift,
                'Status'     => $this->status,
                'Entered by' => $this->enteredBy?->name ?? '-',
            ],
            'rows' => [
                ['group' => 'Recorded values'],
                ['label' => 'Crusher sample stream', 'value' => $this->stream_name],
                ['label' => 'Crushed ore moisture', 'value' => ShiftHelper::num($this->moisture_pct, '%')],
            ],
            'notes'       => $this->notes,
            'review_note' => $this->review_note,
        ];
    }

    /** Values for the Admin edit pop-up (capture names). */
    public function editPayload(): array
    {
        return [
            'stage'  => null,
            'values' => [
                'moisture_reading_date'    => $this->reading_date->toDateString(),
                'moisture_reading_time'    => $this->time_short,
                'moisture_sample_stream'   => $this->stream_name,
                'crushed_ore_moisture_pct' => ShiftHelper::num($this->moisture_pct) ?? '',
                'moisture_notes'           => $this->notes ?? '',
            ],
            'moisture_options' => [],
        ];
    }
}
