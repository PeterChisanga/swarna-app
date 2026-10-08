<?php

namespace App\Support;

use App\Models\CrushingMoistureSample;
use App\Models\CrushingShiftReport;

class Review
{
    /**
     * Records waiting for an Admin decision (sidebar badge).
     * An Open shift report is a shift in progress - it joins the count once Closed.
     */
    public static function pendingCount(): int
    {
        return CrushingMoistureSample::where('status', 'Pending')->count()
            + CrushingShiftReport::where('status', 'Pending')->where('report_stage', 'Closed')->count();
    }
}
