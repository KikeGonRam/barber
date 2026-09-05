<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Notifications\PromotionNotification;
use App\Services\Client\ClientSegmentService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Resuelve la audiencia de una campana y la envia. Reutilizado por el
 * controlador (envio inmediato) y por el comando (campanas programadas).
 */
class CampaignDispatcher
{
    public function __construct(private readonly ClientSegmentService $segments) {}

    /**
     * IDs de usuario de los clientes en el segmento: 'todos', un nivel de
     * lealtad, o 'inactive' (clientes en riesgo — 30+ dias sin cita, ver
     * ClientSegmentService).
     */
    public function audienceUserIds(string $segmento): Collection
    {
        if ($segmento === 'inactive') {
            return $this->segments->userIdsForSegment('inactive');
        }

        $query = Client::query();

        if ($segmento !== 'todos') {
            $query->where('nivel', $segmento);
        }

        return $query->pluck('user_id')->filter()->values();
    }

    /**
     * Conteo de clientes por segmento (para el formulario): 'todos', cada
     * nivel de lealtad, y 'inactive' (clientes en riesgo).
     *
     * @return array<string, int>
     */
    public function segmentCounts(): array
    {
        $counts = ['todos' => Client::count()];

        foreach (array_keys(LoyaltyService::LEVEL_LABELS) as $nivel) {
            $counts[$nivel] = Client::where('nivel', $nivel)->count();
        }

        $counts['inactive'] = $this->segments->userIdsForSegment('inactive')->count();

        return $counts;
    }

    /**
     * Envia la campana a su segmento y la marca como enviada.
     * El via() de la notificacion omite a quien no dio consentimiento.
     *
     * @return int destinatarios del segmento
     */
    public function dispatch(Campaign $campaign): int
    {
        $userIds = $this->audienceUserIds($campaign->segmento);

        if ($userIds->isNotEmpty()) {
            $notification = new PromotionNotification(
                $campaign->titulo,
                $campaign->cuerpo,
                $campaign->cta_label ?: null,
                $campaign->cta_url ?: null,
                (string) $campaign->id,
            );

            User::whereIn('_id', $userIds->all())
                ->chunk(200, fn ($users) => Notification::send($users, $notification));
        }

        $campaign->update([
            'estado' => 'enviada',
            'enviada_en' => now(),
            'destinatarios' => $userIds->count(),
        ]);

        return $userIds->count();
    }
}
