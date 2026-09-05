<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Un cliente solo puede reseñar una vez a un mismo barbero. Antes esto se
 * validaba solo con un ->exists() en la aplicación (BarberReviewService,
 * antes duplicado en ClientBarberController/CatalogController), lo que deja
 * una ventana de condición de carrera entre el chequeo y el insert; este
 * índice es el respaldo real a nivel de base de datos.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        try {
            Schema::connection('mongodb')->table('barber_reviews', function (Blueprint $c) {
                $c->unique(['barber_id', 'client_id']);
            });
        } catch (CommandException $e) {
            // Codigo 85/86 = el indice (o uno equivalente) ya existe.
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        // Las colecciones de MongoDB se borran junto con sus indices; nada que hacer aqui.
    }
};
