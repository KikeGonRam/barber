<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Notifications\PromotionNotification;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        return view('campaigns.index', [
            'levels' => LoyaltyService::LEVEL_LABELS,
            'segmentCounts' => $this->segmentCounts(),
            'campaigns' => Campaign::query()->latest()->limit(10)->get(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $niveles = array_keys(LoyaltyService::LEVEL_LABELS);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'cuerpo' => ['required', 'string', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'cta_url' => ['nullable', 'url', 'max:300'],
            'segmento' => ['required', 'in:todos,'.implode(',', $niveles)],
        ]);

        $userIds = $this->audienceUserIds($data['segmento']);

        if ($userIds->isEmpty()) {
            return back()->withInput()->withErrors(['segmento' => 'El segmento elegido no tiene clientes.']);
        }

        $notification = new PromotionNotification(
            $data['titulo'],
            $data['cuerpo'],
            $data['cta_label'] ?: null,
            $data['cta_url'] ?: null,
        );

        // Envio por lotes; el via() de la notificacion omite a quien no dio
        // consentimiento de marketing.
        User::whereIn('_id', $userIds->all())
            ->chunk(200, fn ($users) => Notification::send($users, $notification));

        Campaign::create([
            'titulo' => $data['titulo'],
            'cuerpo' => $data['cuerpo'],
            'cta_label' => $data['cta_label'] ?: null,
            'cta_url' => $data['cta_url'] ?: null,
            'segmento' => $data['segmento'],
            'destinatarios' => $userIds->count(),
            'enviado_por' => $request->user()?->name,
        ]);

        return back()->with('status', "Campana enviada a {$userIds->count()} cliente(s) del segmento seleccionado (quienes optaron por no recibir promociones se omiten).");
    }

    /**
     * IDs de usuario de los clientes en el segmento.
     */
    private function audienceUserIds(string $segmento)
    {
        $query = Client::query();

        if ($segmento !== 'todos') {
            $query->where('nivel', $segmento);
        }

        return $query->pluck('user_id')->filter()->values();
    }

    /**
     * Conteo de clientes por segmento para mostrarlo en el formulario.
     *
     * @return array<string, int>
     */
    private function segmentCounts(): array
    {
        $counts = ['todos' => Client::count()];

        foreach (array_keys(LoyaltyService::LEVEL_LABELS) as $nivel) {
            $counts[$nivel] = Client::where('nivel', $nivel)->count();
        }

        return $counts;
    }
}
