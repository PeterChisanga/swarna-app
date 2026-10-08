<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crushing_shift_reports', function (Blueprint $table) {
            $table->bigIncrements('shift_report_id');

            // Captured at start of shift
            $table->date('reading_date');
            $table->time('reading_time');
            $table->enum('shift', ['Day', 'Night']);
            $table->string('ore_type', 100);
            $table->string('ore_name_id', 100);
            $table->time('crushing_start_time')->nullable();

            // Captured at end of shift (NULL until the report is closed)
            $table->unsignedBigInteger('moisture_sample_id')->nullable();
            $table->unsignedInteger('trucks_crushed')->nullable();
            $table->time('crushing_stop_time')->nullable();
            $table->decimal('running_hours', 6, 2)->nullable();
            $table->decimal('downtime_hours', 6, 2)->nullable();
            $table->string('downtime_reason', 255)->nullable();
            $table->decimal('stockpile_pct', 6, 2)->nullable();
            $table->text('notes')->nullable();

            // Workflow
            $table->enum('report_stage', ['Open', 'Closed'])->default('Open');
            $table->foreignId('closed_by')->nullable()->constrained('users' , 'user_id');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('entry_group_id')->nullable(); // standard column, always NULL here
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('entered_by')->constrained('users', 'user_id');
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id');
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users', 'user_id');
            $table->dateTime('last_edited_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['reading_date', 'shift'], 'uq_shift_report_date_shift');
            $table->index('moisture_sample_id');
            $table->foreign('moisture_sample_id')
                ->references('moisture_id')->on('crushing_moisture_samples');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crushing_shift_reports');
    }
};
