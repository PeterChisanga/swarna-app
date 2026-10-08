<?php

namespace App\Models\Concerns;

use App\Support\ShiftHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared by tables that have reading_date, reading_time, shift and status.
 */
trait HasOperatingDate
{
    /** Rows belonging to an operating date (06:00 of the date up to 05:59 of the next day). */
    public function scopeOnOperatingDate(Builder $query, string $date): Builder
    {
        $start = ShiftHelper::dayStart() . ':00';
        $next  = Carbon::parse($date)->addDay()->toDateString();

        return $query->where(function (Builder $w) use ($date, $next, $start) {
            $w->where(function (Builder $a) use ($date, $start) {
                $a->whereDate('reading_date', $date)->where('reading_time', '>=', $start);
            })->orWhere(function (Builder $b) use ($next, $start) {
                $b->whereDate('reading_date', $next)->where('reading_time', '<', $start);
            });
        });
    }

    /** Operating-date + optional shift filter (the topbar filters). */
    public function scopeInView(Builder $query, string $date, string $shift = 'all'): Builder
    {
        return $query->onOperatingDate($date)
            ->when($shift !== 'all', fn (Builder $q) => $q->where('shift', $shift));
    }

    /** Rejected records are hidden from dashboards and lists (PRD). Pending is normal. */
    public function scopeNotRejected(Builder $query): Builder
    {
        return $query->where('status', '!=', 'Rejected');
    }

    public function getOperatingDateAttribute(): string
    {
        return ShiftHelper::operatingDate($this->reading_date->toDateString(), (string) $this->reading_time);
    }

    public function getTimeShortAttribute(): string
    {
        return substr((string) $this->reading_time, 0, 5);
    }

    public function getSortKeyAttribute(): string
    {
        return $this->reading_date->toDateString() . ' ' . $this->reading_time;
    }
}
