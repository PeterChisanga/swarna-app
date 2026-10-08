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
        Schema::create('streams', function (Blueprint $table) {
            $table->id('stream_id');

            $table->foreignId('section_id')
                ->constrained('sections', 'section_id');

            $table->string('stream_name', 100);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // A stream name must be unique within its section.
            $table->unique(
                ['section_id', 'stream_name'],
                'uq_section_stream'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};
