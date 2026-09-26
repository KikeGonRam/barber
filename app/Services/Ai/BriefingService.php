<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

use function Illuminate\Support\defer;

/**
 * Resumen corto en lenguaje natural para el inicio de recepción y administración.
 *
 * Nunca hace esperar a quien lo pide: responde al instante con el último resumen de la IA que esté
 * guardado o, si no hay, con uno armado por reglas a partir de los mismos números. Cuando el
 * guardado ya está viejo (o no existe), pide uno nuevo a la IA DESPUÉS de enviar la respuesta
 * (defer), con un candado para que dos peticiones no lo generen a la vez.
 */
class BriefingService
{
    private const FRESH_SECONDS = 900;

    public function __construct(private readonly LocalAi $ai) {}

    /**
     * @param  array<string, int|float|string|null>  $facts  números del día ya calculados
     * @return array{text: string, source: string, generated_at: ?string}
     */
    public function briefing(string $audience, array $facts): array
    {
        $key = "ai_briefing:{$audience}:".now()->toDateString();
        /** @var array{text: string, generated_at: string, facts_hash: string}|null $cached */
        $cached = Cache::get($key);
        $factsHash = md5((string) json_encode($facts));

        // Viejo: no hay, pasaron 15 min, o cambiaron los números (con al menos 1 min entre intentos).
        $age = $cached === null ? null : Carbon::parse($cached['generated_at'])->diffInSeconds(now(), true);
        $stale = $age === null
            || $age > self::FRESH_SECONDS
            || ($cached['facts_hash'] !== $factsHash && $age > 60);

        if ($stale && $this->ai->available()) {
            defer(function () use ($audience, $facts, $key, $factsHash) {
                Cache::lock($key.':lock', 60)->get(function () use ($audience, $facts, $key, $factsHash) {
                    $text = $this->ai->complete($this->prompt($audience, $facts), 90, 45.0, 0.3);
                    if ($text !== null) {
                        Cache::put($key, [
                            'text' => $this->clean($text),
                            'generated_at' => now()->toIso8601String(),
                            'facts_hash' => $factsHash,
                        ], 86400);
                    }
                });
            });
        }

        if ($cached !== null) {
            return ['text' => $cached['text'], 'source' => 'ia', 'generated_at' => $cached['generated_at']];
        }

        return ['text' => $this->byRules($audience, $facts), 'source' => 'reglas', 'generated_at' => null];
    }

    /** @param array<string, int|float|string|null> $facts */
    private function prompt(string $audience, array $facts): string
    {
        $who = $audience === 'admin' ? 'el administrador de la barbería' : 'la recepcionista de la barbería';
        $lines = collect($facts)->map(fn ($v, $k) => "- {$k}: ".($v ?? 'sin dato'))->implode("\n");

        return "Eres el asistente de UrbanBlade, una barbería en México. Escribe para {$who} un resumen "
            .'del día en español de México, en 2 frases cortas (máximo 40 palabras en total), tono amable '
            .'y práctico: primero cómo va el día y luego lo que conviene atender primero. Usa SOLO estos '
            ."datos, no inventes números ni nombres, no uses listas ni emojis.\n\nDatos de hoy:\n{$lines}\n\nResumen:";
    }

    private function clean(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        return mb_strlen($text) > 320 ? rtrim(mb_substr($text, 0, 317)).'…' : $text;
    }

    /**
     * Versión sin IA: mismas prioridades, frases fijas.
     *
     * @param  array<string, int|float|string|null>  $facts
     */
    private function byRules(string $audience, array $facts): string
    {
        $citas = (int) ($facts['citas_hoy'] ?? 0);
        $parts = [$citas === 0 ? 'Hoy no hay citas en la agenda.' : "Hoy hay {$citas} ".($citas === 1 ? 'cita' : 'citas').' en la agenda.'];

        $pendientes = [];
        if (($n = (int) ($facts['citas_por_cobrar'] ?? 0)) > 0) {
            $pendientes[] = "{$n} por cobrar";
        }
        if (($n = (int) ($facts['pedidos_por_entregar'] ?? 0)) > 0) {
            $pendientes[] = "{$n} ".($n === 1 ? 'pedido' : 'pedidos').' por entregar';
        }
        if (($n = (int) ($facts['productos_con_stock_bajo'] ?? 0)) > 0) {
            $pendientes[] = "{$n} ".($n === 1 ? 'producto' : 'productos').' con stock bajo';
        }

        $parts[] = $pendientes === []
            ? 'No hay pendientes urgentes.'
            : 'Conviene atender primero: '.implode(', ', $pendientes).'.';

        if ($audience === 'admin' && isset($facts['crecimiento_ingresos_pct'])) {
            $g = (float) $facts['crecimiento_ingresos_pct'];
            $parts[] = 'Los ingresos del mes van '.($g >= 0 ? "{$g}% arriba" : abs($g).'% abajo').' del mes pasado.';
        }

        return implode(' ', $parts);
    }
}
