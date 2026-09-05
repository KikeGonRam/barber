<?php

namespace Tests\Unit;

use App\Models\AnalyticsInsight;
use PHPUnit\Framework\TestCase;

/**
 * toDashboardCardArray() nació en la migración del dashboard a Inertia+Vue
 * (ver .claude/skills/inertia-vue-migration/SKILL.md, Fase 4): antes esta
 * lógica de truncado/porcentaje vivía inline en
 * components/analytics-insights.blade.php y nunca se probó directamente.
 */
class AnalyticsInsightTest extends TestCase
{
    private function makeInsight(array $attributes): AnalyticsInsight
    {
        $insight = new AnalyticsInsight;
        foreach ($attributes as $key => $value) {
            $insight->{$key} = $value;
        }

        return $insight;
    }

    public function test_maps_basic_fields_and_defaults_color_to_gold(): void
    {
        $insight = $this->makeInsight([
            'titulo' => 'Segmento premium',
            'valor_destacado' => '18 clientes',
            'mensaje' => 'Un mensaje corto.',
        ]);

        $card = $insight->toDashboardCardArray();

        $this->assertSame('Segmento premium', $card['titulo']);
        $this->assertSame('18 clientes', $card['dato']);
        $this->assertSame('gold', $card['color']);
    }

    public function test_resolves_visual_label_from_the_insight_type(): void
    {
        $insight = $this->makeInsight(['tipo' => 'clientes_en_riesgo', 'mensaje' => '']);

        $this->assertSame('Distribución', $insight->toDashboardCardArray()['visual_label']);
    }

    public function test_visual_label_defaults_to_comparativo_without_a_known_type(): void
    {
        // Sin 'tipo' ni 'grafica', visualTypeFor() cae al default 'bar' (Comparativo).
        $insight = $this->makeInsight(['mensaje' => '']);

        $this->assertSame('Comparativo', $insight->toDashboardCardArray()['visual_label']);
    }

    public function test_short_message_is_not_truncated(): void
    {
        $insight = $this->makeInsight(['mensaje' => 'Un hallazgo corto y directo.']);

        $card = $insight->toDashboardCardArray();

        $this->assertFalse($card['is_truncated']);
        $this->assertSame('Un hallazgo corto y directo.', $card['brief']);
    }

    public function test_long_message_is_truncated_to_34_words(): void
    {
        $mensaje = implode(' ', array_fill(0, 40, 'palabra'));
        $insight = $this->makeInsight(['mensaje' => $mensaje]);

        $card = $insight->toDashboardCardArray();

        $this->assertTrue($card['is_truncated']);
        $this->assertSame($mensaje, $card['mensaje']);
        $this->assertNotSame($mensaje, $card['brief']);
        $this->assertStringEndsWith('...', $card['brief']);
    }

    public function test_extracts_a_percentage_from_the_highlighted_value(): void
    {
        $insight = $this->makeInsight(['valor_destacado' => '23.5% de cancelaciones', 'mensaje' => '']);

        $this->assertSame(23.5, $insight->toDashboardCardArray()['progress_value']);
    }

    public function test_progress_value_is_null_without_a_percentage(): void
    {
        $insight = $this->makeInsight(['valor_destacado' => '18 clientes', 'mensaje' => '']);

        $this->assertNull($insight->toDashboardCardArray()['progress_value']);
    }
}
