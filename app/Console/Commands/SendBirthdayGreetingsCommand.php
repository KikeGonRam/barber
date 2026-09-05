<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Notifications\Loyalty\ClientBirthdayNotification;
use App\Services\Loyalty\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Corre a diario: felicita y regala puntos de lealtad a los clientes que
 * cumplen años hoy (Client::fecha_nacimiento, dato ya capturado en el
 * perfil pero que antes nunca se usaba para nada). El dato es opcional, asi
 * que solo alcanza a quien lo haya llenado.
 */
class SendBirthdayGreetingsCommand extends Command
{
    protected $signature = 'clients:send-birthday-greetings {--dry-run : Solo muestra a quien se felicitaria, sin escribir}';

    protected $description = 'Felicita y regala puntos de lealtad a los clientes que cumplen anos hoy';

    public function handle(LoyaltyService $loyalty): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        // fecha_nacimiento es opcional, asi que solo se trae a quien lo
        // haya llenado; el conjunto suele ser chico, se filtra mes/dia en
        // PHP para evitar comparar por string contra un campo 'date' (ver
        // el mismo problema ya documentado en BarberDashboardController).
        $candidates = Client::whereNotNull('fecha_nacimiento')
            ->with('user')
            ->get()
            ->filter(fn (Client $c) => $c->fecha_nacimiento && self::isBirthdayToday(Carbon::parse($c->fecha_nacimiento), $today));

        if ($candidates->isEmpty()) {
            $this->info('Nadie cumple años hoy.');

            return self::SUCCESS;
        }

        $this->info("{$candidates->count()} cliente(s) cumplen años hoy.");

        if ($dryRun) {
            return self::SUCCESS;
        }

        $felicitados = 0;

        foreach ($candidates as $client) {
            // Evita duplicar el regalo si el comando se corre dos veces el
            // mismo dia (ej. reintento manual tras una falla a medias).
            $yaFelicitado = LoyaltyTransaction::where('client_id', (string) $client->id)
                ->where('descripcion', LoyaltyService::BIRTHDAY_TRANSACTION_DESCRIPTION)
                ->where('created_at', '>=', $today)
                ->exists();

            if ($yaFelicitado) {
                continue;
            }

            $loyalty->awardBirthdayPoints($client);
            $felicitados++;

            try {
                $client->user?->notify(new ClientBirthdayNotification);
            } catch (\Throwable) {
            }
        }

        $this->info("{$felicitados} cliente(s) felicitados con ".LoyaltyService::BIRTHDAY_POINTS.' puntos de regalo.');

        return self::SUCCESS;
    }

    // Compara solo mes/dia; a los nacidos el 29 de febrero se les felicita
    // el 28 de febrero en años no bisiestos, para no dejarlos sin regalo
    // cada tres de cada cuatro años. Publico y estatico para poder probar
    // el caso especial del 29 de febrero sin depender de la fecha real.
    public static function isBirthdayToday(?Carbon $fechaNacimiento, Carbon $today): bool
    {
        if (! $fechaNacimiento) {
            return false;
        }

        if ($fechaNacimiento->month === $today->month && $fechaNacimiento->day === $today->day) {
            return true;
        }

        return $fechaNacimiento->month === 2 && $fechaNacimiento->day === 29
            && $today->month === 2 && $today->day === 28
            && ! $today->isLeapYear();
    }
}
