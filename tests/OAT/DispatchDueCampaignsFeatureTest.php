<?php

namespace Tests\OAT;

use App\Models\Campaign;
use App\Services\Campaign\CampaignDispatcher;
use Tests\TestCase;

/**
 * Apagar las rutas de campañas (config/features.php) no basta: `campaigns:dispatch-due` corre cada 5 minutos
 * y seguiría enviando correo promocional de una campaña ya programada. Con el interruptor apagado el comando
 * no debe despachar nada ni tocar la campaña. El comando lee la configuración al ejecutarse (no al registrar
 * rutas), así que aquí sí basta con config().
 */
class DispatchDueCampaignsFeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Campaign::query()->delete();

        parent::tearDown();
    }

    private function dueCampaign(): Campaign
    {
        return Campaign::create([
            'titulo' => 'Promo vencida',
            'cuerpo' => 'Cuerpo',
            'segmento' => 'todos',
            'destinatarios' => 0,
            'estado' => 'programada',
            'programada_para' => now()->subMinute(),
        ]);
    }

    public function test_el_comando_no_envia_nada_con_las_campanas_apagadas(): void
    {
        config(['features.campaigns' => false]);
        $campaign = $this->dueCampaign();

        $this->mock(CampaignDispatcher::class)->shouldNotReceive('dispatch');

        $this->artisan('campaigns:dispatch-due')->assertSuccessful();

        $this->assertSame('programada', $campaign->fresh()->estado);
    }

    public function test_el_comando_despacha_las_vencidas_con_las_campanas_encendidas(): void
    {
        config(['features.campaigns' => true]);
        $campaign = $this->dueCampaign();

        $this->mock(CampaignDispatcher::class)
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn (Campaign $c) => (string) $c->id === (string) $campaign->id)
            ->andReturn(0);

        $this->artisan('campaigns:dispatch-due')->assertSuccessful();
    }
}
