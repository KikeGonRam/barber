<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Membresía recurrente (roadmap P1): un cliente no puede tener dos
 * membresías activas/pendientes/con pago fallido a la vez -- índice único
 * filtrado por `bloquea_membresia` (Mongo solo permite igualdad en
 * partialFilterExpression, no $in/$nin, de ahí el booleano espejo en vez de
 * filtrar directo por `estado != cancelada`), mismo patrón que
 * `waitlist_active_entry_unique`.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        try {
            Schema::connection('mongodb')->table('client_memberships', function (Blueprint $c) {
                $c->unique(['client_id', 'bloquea_membresia'], 'client_membership_active_unique', null, [
                    'partialFilterExpression' => [
                        'bloquea_membresia' => true,
                    ],
                ]);
            });
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('client_memberships', function (Blueprint $c) {
                $c->index(['stripe_subscription_id'], 'client_membership_stripe_subscription_index');
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
            Schema::connection('mongodb')->table('client_memberships', function (Blueprint $c) {
                $c->dropIndex('client_membership_active_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('client_memberships', function (Blueprint $c) {
                $c->dropIndex('client_membership_stripe_subscription_index');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
