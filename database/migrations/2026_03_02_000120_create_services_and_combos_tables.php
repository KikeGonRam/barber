<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('categoria');
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('duracion_min');
            $table->string('imagen')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('service_combos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('precio_combo', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('combo_service', function (Blueprint $table) {
            $table->foreignId('combo_id')->constrained('service_combos')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();

            $table->primary(['combo_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_service');
        Schema::dropIfExists('service_combos');
        Schema::dropIfExists('services');
    }
};
