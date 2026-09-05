<?php

namespace App\Services\Client;

use App\Models\Appointment;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula a qué segmento pertenece un cliente (vip/new/active/inactive) a
 * partir de su historial de citas. Antes esta lógica vivía duplicada —
 * ClientAdminController la calculaba para el panel de administración, y por
 * separado el proyecto Spark calculaba su propia versión de "segmentación de
 * clientes" en batch, sin que ambas coincidieran necesariamente. Este
 * servicio es ahora la única fuente de verdad en Laravel, usada tanto por el
 * panel de clientes como por el sistema de campañas (para poder targetear
 * "clientes en riesgo" con una campaña real).
 *
 * Prioridad: nuevo > inactivo > vip > activo. Un cliente inactivo (30+ días
 * sin cita) cuenta como "en riesgo" sin importar cuántas citas tuvo antes —
 * un VIP que dejó de venir es a quien más conviene recontactar, no alguien a
 * quien "proteger" del segmento de riesgo por su historial pasado.
 */
class ClientSegmentService
{
    private const DIAS_NUEVO = 14;

    private const DIAS_INACTIVO = 30;

    private const CITAS_VIP = 10;

    /**
     * Segmento de un cliente ya con su conteo de citas y fecha de la última
     * precalculados (para reusar en lote sin repetir queries — ver
     * segmentAllClients()).
     */
    public function segment(Client $client, int $apptCount, mixed $lastFecha): string
    {
        $daysSinceJoin = (int) optional($client->created_at)->diffInDays(Carbon::now());
        if ($daysSinceJoin <= self::DIAS_NUEVO) {
            return 'new';
        }

        $daysSinceLast = $lastFecha
            ? (int) Carbon::parse($lastFecha)->diffInDays(Carbon::now())
            : 999;

        if ($daysSinceLast > self::DIAS_INACTIVO) {
            return 'inactive';
        }

        if ($apptCount > self::CITAS_VIP) {
            return 'vip';
        }

        return 'active';
    }

    /**
     * Segmento de TODOS los clientes en una sola pasada (1 query de citas en
     * lote, no N). Devuelve un mapa client_id (string) => segmento.
     *
     * @return Collection<string, string>
     */
    public function segmentAllClients(): Collection
    {
        $clients = Client::all();
        $clientIds = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        $allAppts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id', 'fecha'])
            ->groupBy(fn ($a) => (string) $a->client_id);

        return $clients->mapWithKeys(function (Client $client) use ($allAppts) {
            $appts = $allAppts->get((string) $client->id, collect());
            $lastFecha = $appts->sortByDesc(fn ($a) => (string) $a->fecha)->first()?->fecha;

            return [(string) $client->id => $this->segment($client, $appts->count(), $lastFecha)];
        });
    }

    /**
     * IDs de usuario (Client->user_id) de los clientes en un segmento dado —
     * lo que necesita CampaignDispatcher para poder enviarles algo.
     */
    public function userIdsForSegment(string $segmento): Collection
    {
        $porSegmento = $this->segmentAllClients();
        $idsEnSegmento = $porSegmento->filter(fn ($seg) => $seg === $segmento)->keys();

        return Client::query()
            ->whereIn('_id', $idsEnSegmento->all())
            ->pluck('user_id')
            ->filter()
            ->values();
    }

    /**
     * Conteo de clientes por segmento (vip/new/active/inactive).
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $segments = ['vip' => 0, 'new' => 0, 'active' => 0, 'inactive' => 0];

        foreach ($this->segmentAllClients() as $segmento) {
            $segments[$segmento] = ($segments[$segmento] ?? 0) + 1;
        }

        return $segments;
    }
}
