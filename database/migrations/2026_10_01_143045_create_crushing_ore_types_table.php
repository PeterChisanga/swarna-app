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
        // "Controlled selection (list maintained by Admin)" - PRD 8.1.1 crushing_ore_type.
        Schema::create('crushing_ore_types', function (Blueprint $table) {
            $table->id('ore_type_id');
            $table->string('ore_type', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crushing_ore_types');
    }
};
