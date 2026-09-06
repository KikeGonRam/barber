<?php

namespace App\Http\Controllers\Api\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaign\CampaignDispatcher;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de campañas de marketing por segmento (Fase 9.9), puerto de
 * Campaign\CampaignController (web): mismo CampaignDispatcher, mismas reglas
 * de segmento/envío inmediato o programado. Solo administradores.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignDispatcher $dispatcher) {}

    /**
     * Niveles de lealtad disponibles, conteo de clientes por segmento y las
     * últimas 10 campañas enviadas/programadas.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $campaigns = Campaign::query()->latest()->limit(10)->get();

        return response()->json([
            'levels' => LoyaltyService::LEVEL_LABELS,
            'segment_counts' => $this->dispatcher->segmentCounts(),
            'data' => $campaigns->map(fn (Campaign $c) => [
                'id' => $c->id,
                'titulo' => $c->titulo,
                'cuerpo' => $c->cuerpo,
                'cta_label' => $c->cta_label,
                'cta_url' => $c->cta_url,
                'segmento' => $c->segmento,
                'destinatarios' => $c->destinatarios,
                'estado' => $c->estado,
                'programada_para' => optional($c->programada_para)->toAtomString(),
                'enviada_en' => optional($c->enviada_en)->toAtomString(),
                'created_at' => optional($c->created_at)->toAtomString(),
                'opens' => $c->opensCount(),
                'clicks' => $c->clicksCount(),
                'open_rate' => $c->rate($c->opensCount()),
                'click_rate' => $c->rate($c->clicksCount()),
            ])->values(),
        ]);
    }

    /**
     * Crea y despacha (ahora o programada) una campaña dirigida a un
     * segmento de clientes; rechaza segmentos sin clientes antes de crear
     * el registro.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $niveles = array_keys(LoyaltyService::LEVEL_LABELS);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'cuerpo' => ['required', 'string', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'cta_url' => ['nullable', 'url', 'max:300'],
            'segmento' => ['required', 'in:todos,inactive,'.implode(',', $niveles)],
            'modo' => ['required', 'in:ahora,programar'],
            'programada_para' => ['required_if:modo,programar', 'nullable', 'date', 'after:now'],
        ]);

        if ($this->dispatcher->audienceUserIds($data['segmento'])->isEmpty()) {
            return response()->json(['message' => 'El segmento elegido no tiene clientes.'], 422);
        }

        $campaign = Campaign::create([
            'titulo' => $data['titulo'],
            'cuerpo' => $data['cuerpo'],
            'cta_label' => ($data['cta_label'] ?? null) ?: null,
            'cta_url' => ($data['cta_url'] ?? null) ?: null,
            'segmento' => $data['segmento'],
            'destinatarios' => 0,
            'enviado_por' => $request->user()?->name,
            'estado' => 'programada',
            'programada_para' => $data['modo'] === 'programar' ? $data['programada_para'] : null,
        ]);

        if ($data['modo'] === 'ahora') {
            $count = $this->dispatcher->dispatch($campaign);

            return response()->json([
                'message' => "Campaña enviada a {$count} cliente(s) del segmento (quienes desactivaron promociones se omiten).",
            ], 201);
        }

        $cuando = $campaign->programada_para->translatedFormat('d M Y, H:i');

        return response()->json([
            'message' => "Campaña programada para el {$cuando}.",
        ], 201);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
