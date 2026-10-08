<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Shift and operating-date logic, ported from derive() in the mockup app.js.
 *
 * - Day shift = day_shift_start <= time < day_shift_end, otherwise Night.
 * - A reading made before the day-shift start belongs to the PREVIOUS
 *   operating date (a night shift that ends after midnight keeps the date
 *   it started on).
 */
class ShiftHelper
{
    public static function dayStart(): string
    {
        return config('plant.day_shift_start', '08:00');
    }

    public static function dayEnd(): string
    {
        return config('plant.day_shift_end', '18:00');
    }

    public static function shiftFor(string $time): string
    {
        $t = substr($time, 0, 5);

        return ($t >= self::dayStart() && $t < self::dayEnd()) ? 'Day' : 'Night';
    }

    public static function operatingDate(string $date, string $time): string
    {
        $d = Carbon::parse($date);

        if (substr($time, 0, 5) < self::dayStart()) {
            $d->subDay();
        }

        return $d->toDateString();
    }

    /** The operating date "right now" (used as the default page filter). */
    public static function currentOperatingDate(): string
    {
        return self::operatingDate(now()->toDateString(), now()->format('H:i'));
    }

    /** Trim trailing zeros: "5.40" -> "5.4", "7.2000" -> "7.2". NULL stays NULL (never zero). */
    public static function num($value, string $unit = ''): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = (string) $value;

        if (str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
        }

        return $s . ($unit !== '' ? ' ' . $unit : '');
    }

    public static function sampleDate(string $opDate, string $time): string
    {
        $d = Carbon::parse($opDate);

        if (substr($time, 0, 5) < self::dayStart()) {
            $d->addDay();
        }

        return $d->toDateString();
    }
}
