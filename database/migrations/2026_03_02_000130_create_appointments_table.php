<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained('barbers')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->enum('estado', ['pendiente', 'confirmada', 'en_proceso', 'completada', 'cancelada', 'no_asistio'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->decimal('precio_cobrado', 10, 2)->nullable();
            $table->string('motivo_reagendamiento')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['barber_id', 'fecha', 'hora_inicio']);
            $table->index(['client_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
