<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Several reports can exist for one date and shift (material change).
        // Only the exact start time must stay unique.
        Schema::table('crushing_shift_reports', function (Blueprint $table) {
            $table->dropUnique('uq_shift_report_date_shift');
            $table->unique(['reading_date', 'reading_time'], 'uq_shift_report_date_time');
        });
    }

    public function down(): void
    {
        Schema::table('crushing_shift_reports', function (Blueprint $table) {
            $table->dropUnique('uq_shift_report_date_time');
            $table->unique(['reading_date', 'shift'], 'uq_shift_report_date_shift');
        });
    }
};
