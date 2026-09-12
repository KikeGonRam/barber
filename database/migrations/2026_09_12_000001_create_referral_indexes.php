<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Programa de referidos (roadmap P1): un cliente solo puede haber sido
 * referido UNA VEZ en toda su vida -- a diferencia de otros índices de este
 * proyecto, este no necesita un campo derivado tipo `activa`, porque la
 * unicidad aplica siempre (pendiente o completado), no solo mientras algo
 * sigue "activo".
 *
 * Backfill necesario antes del índice único de codigo_referido: los
 * clientes creados antes de esta feature no tienen el campo, y varios
 * documentos con el mismo valor ausente violarían la unicidad (Mongo trata
 * el campo faltante como null indexable, y null no es único entre sí).
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Client::whereNull('codigo_referido')->orWhere('codigo_referido', '')->get(['_id'])->each(function (Client $client) {
            do {
                $code = Str::upper(Str::random(6));
            } while (Client::where('codigo_referido', $code)->exists());

            $client->update(['codigo_referido' => $code]);
        });

        try {
            Schema::connection('mongodb')->table('referrals', function (Blueprint $c) {
                $c->unique(['referee_client_id'], 'referral_referee_unique');
            });
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('clients', function (Blueprint $c) {
                $c->unique(['codigo_referido'], 'clients_codigo_referido_unique');
            });
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::connection('mongodb')->table('referrals', function (Blueprint $c) {
                $c->dropIndex('referral_referee_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('clients', function (Blueprint $c) {
                $c->dropIndex('clients_codigo_referido_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
