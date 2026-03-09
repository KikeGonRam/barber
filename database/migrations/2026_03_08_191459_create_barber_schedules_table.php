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
        Schema::create('barber_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0 (Sun) to 6 (Sat)
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_working')->default(true);
            $table->timestamps();

            $table->unique(['barber_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barber_schedules');
    }
};
