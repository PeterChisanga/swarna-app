<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** PRD 8.1.2 - DB dictionary: crushing_moisture_samples */
    public function up(): void
    {
        Schema::create('crushing_moisture_samples', function (Blueprint $table) {
            $table->bigIncrements('moisture_id');
            $table->date('reading_date');
            $table->time('reading_time');
            $table->enum('shift', ['Day', 'Night']);
            $table->string('stream_name', 100);
            $table->decimal('moisture_pct', 10, 4);
            $table->text('notes')->nullable();
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

            $table->unique(['reading_date', 'reading_time', 'stream_name'], 'uq_moisture_date_time_stream');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crushing_moisture_samples');
    }
};
