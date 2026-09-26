<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

use function Illuminate\Support\defer;

/**
 * Resumen corto en lenguaje natural para el inicio de recepción y administración.
 *
 * Las cifras SIEMPRE las pone el sistema (byRules): el modelo pequeño llegó a decir "no hay citas"
 * con 2 citas en la agenda. La IA solo aporta un consejo corto que se agrega al final, y se
 * descarta si trae números, niega algo que sí existe o es demasiado largo.
 *
 * Nunca hace esperar: responde al instante con el último consejo guardado (o sin consejo) y, si
 * ya está viejo, pide uno nuevo a la IA DESPUÉS de enviar la respuesta (defer), con un candado para
 * que dos peticiones no lo generen a la vez.
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
                    $tip = $this->safeTip($this->ai->complete($this->prompt($audience, $facts), 50, 45.0, 0.3));
                    if ($tip !== null) {
                        Cache::put($key, [
                            'text' => $tip,
                            'generated_at' => now()->toIso8601String(),
                            'facts_hash' => $factsHash,
                        ], 86400);
                    }
                });
            });
        }

        $factsText = $this->byRules($audience, $facts);

        // El consejo guardado solo se usa si es de estos mismos números (si cambiaron, podría no aplicar).
        if ($cached !== null && $cached['facts_hash'] === $factsHash) {
            return ['text' => $factsText.' '.$cached['text'], 'source' => 'ia', 'generated_at' => $cached['generated_at']];
        }

        return ['text' => $factsText, 'source' => 'reglas', 'generated_at' => null];
    }

    /** @param array<string, int|float|string|null> $facts */
    private function prompt(string $audience, array $facts): string
    {
        $who = $audience === 'admin' ? 'el administrador de la barbería' : 'la recepcionista de la barbería';
        $lines = collect($facts)->map(fn ($v, $k) => "- {$k}: ".($v ?? 'sin dato'))->implode("\n");

        return "Eres el asistente de UrbanBlade, una barbería en México. Con estos datos de hoy, escribe para {$who} "
            .'UN solo consejo práctico de una frase (máximo 20 palabras), en español de México, empezando con un '
            .'verbo (por ejemplo: "Aprovecha…", "Revisa…", "Prepara…"). No repitas cifras ni escribas números, '
            ."no saludes, no uses listas ni emojis.\n\nDatos de hoy:\n{$lines}\n\nConsejo:";
    }

    /**
     * El consejo de la IA solo se acepta si es breve, no trae cifras (las cifras son del sistema) y no
     * niega nada ("no hay…"), que es donde el modelo pequeño se equivoca. Si no pasa, no se usa.
     */
    private function safeTip(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        // Sin espacios de más ni comillas alrededor (regex /u: trim() cortaría bytes de «» o ¿).
        $tip = preg_replace('/^[\s"\'«»]+|[\s"\'«»]+$/u', '', preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '') ?? '';
        $tip = preg_replace('/^(consejo|tip)\s*:\s*/iu', '', $tip) ?? $tip;
        $words = str_word_count($tip, 0, 'áéíóúñÁÉÍÓÚÑü');

        if ($tip === '' || $words < 3 || $words > 26
            || preg_match('/\d/', $tip)
            || preg_match('/\b(no hay|ning[uú]n|ninguna|nada|hola|asistente|datos)\b/iu', $tip)) {
            return null;
        }

        $tip = mb_strtoupper(mb_substr($tip, 0, 1)).mb_substr($tip, 1);

        return preg_match('/[.!?]$/u', $tip) ? $tip : $tip.'.';
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
