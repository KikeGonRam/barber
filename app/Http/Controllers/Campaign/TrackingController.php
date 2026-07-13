<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Endpoints publicos de seguimiento de campanas (los golpea el cliente de
 * correo, sin sesion). El tracking de apertura es aproximado: muchos clientes
 * bloquean imagenes.
 */
class TrackingController extends Controller
{
    // GIF transparente de 1x1.
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $campaign, string $user): Response
    {
        $this->record($campaign, 'opened_by', $user);

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(string $campaign, string $user): RedirectResponse
    {
        $model = $this->record($campaign, 'clicked_by', $user);

        // Redirige SOLO al destino guardado en la campana (evita open-redirect).
        $target = $model?->cta_url;
        if (! $target) {
            try {
                $target = route('services.public.index');
            } catch (\Throwable $e) {
                $target = url('/');
            }
        }

        return redirect()->away($target);
    }

    private function record(string $campaignId, string $column, string $userId): ?Campaign
    {
        try {
            $campaign = Campaign::find($campaignId);
            if ($campaign && $userId !== '') {
                // push con unique=true equivale a $addToSet: cuenta unicos.
                $campaign->push($column, $userId, true);
            }

            return $campaign;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
