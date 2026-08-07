<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaign\CampaignDispatcher;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel web de campañas de marketing por segmento de fidelidad (nivel de lealtad),
 * envío inmediato o programado. Uso exclusivo de administración.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignDispatcher $dispatcher) {}

    /**
     * Panel de campañas: niveles de lealtad disponibles, conteo de clientes por segmento
     * y las últimas 10 campañas enviadas/programadas.
     */
    public function index(): View
    {
        return view('campaigns.index', [
            'levels' => LoyaltyService::LEVEL_LABELS,
            'segmentCounts' => $this->dispatcher->segmentCounts(),
            'campaigns' => Campaign::query()->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Crea y despacha (ahora o programada) una campaña dirigida a un segmento de clientes
     * por nivel de lealtad; rechaza segmentos sin clientes antes de crear el registro.
     */
    public function send(Request $request): RedirectResponse
    {
        $niveles = array_keys(LoyaltyService::LEVEL_LABELS);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'cuerpo' => ['required', 'string', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'cta_url' => ['nullable', 'url', 'max:300'],
            'segmento' => ['required', 'in:todos,'.implode(',', $niveles)],
            'modo' => ['required', 'in:ahora,programar'],
            'programada_para' => ['required_if:modo,programar', 'nullable', 'date', 'after:now'],
        ]);

        if ($this->dispatcher->audienceUserIds($data['segmento'])->isEmpty()) {
            return back()->withInput()->withErrors(['segmento' => 'El segmento elegido no tiene clientes.']);
        }

        $campaign = Campaign::create([
            'titulo' => $data['titulo'],
            'cuerpo' => $data['cuerpo'],
            'cta_label' => $data['cta_label'] ?: null,
            'cta_url' => $data['cta_url'] ?: null,
            'segmento' => $data['segmento'],
            'destinatarios' => 0,
            'enviado_por' => $request->user()?->name,
            'estado' => 'programada',
            'programada_para' => $data['modo'] === 'programar' ? $data['programada_para'] : null,
        ]);

        // Envio inmediato: se despacha ya. Programada: queda pendiente para
        // que el comando campaigns:dispatch-due la envie al vencer.
        if ($data['modo'] === 'ahora') {
            $count = $this->dispatcher->dispatch($campaign);

            return back()->with('status', "Campana enviada a {$count} cliente(s) del segmento (quienes desactivaron promociones se omiten).");
        }

        $cuando = $campaign->programada_para->translatedFormat('d M Y, H:i');

        return back()->with('status', "Campana programada para el {$cuando}.");
    }
}
