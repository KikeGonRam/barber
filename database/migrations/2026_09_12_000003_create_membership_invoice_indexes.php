<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Idempotencia real de MembershipInvoice: encontrado en vivo (2026-09-11,
 * verificando el flujo end-to-end tras habilitar los permisos de Stripe) que
 * Stripe puede entregar el mismo webhook invoice.payment_succeeded dos veces
 * casi al mismo tiempo -- el check-then-create de aplicación en
 * MembershipService::recordSuccessfulInvoice() no es atómico, así que ambas
 * peticiones pasaron el `exists()` antes de que cualquiera terminara el
 * `create()`, duplicando el ingreso en el corte de caja. Mismo patrón que
 * `waitlist_active_entry_unique` / `client_membership_active_unique`.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        try {
            Schema::connection('mongodb')->table('membership_invoices', function (Blueprint $c) {
                $c->unique(['stripe_invoice_id'], 'membership_invoice_stripe_invoice_unique');
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
            Schema::connection('mongodb')->table('membership_invoices', function (Blueprint $c) {
                $c->dropIndex('membership_invoice_stripe_invoice_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
