<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente único del modelo local (Ollama) para todo barber: Bladebot, resúmenes y predicciones.
 *
 * Regla: la IA nunca frena un flujo. Cada llamada tiene un límite de tiempo corto y, si Ollama
 * falla o tarda de más, se abre un "cortacircuito" por unos minutos: mientras está abierto nadie
 * espera, complete() devuelve null al instante y quien llama usa su versión sin IA.
 */
class LocalAi
{
    private const DOWN_KEY = 'local_ai:down';

    public function enabled(): bool
    {
        return config('chatbot.ai.provider') === 'ollama'
            && (string) config('chatbot.ai.ollama.url', '') !== '';
    }

    /** ¿Se puede intentar ahora? (habilitada y sin el cortacircuito abierto). */
    public function available(): bool
    {
        return $this->enabled() && ! Cache::has(self::DOWN_KEY);
    }

    /**
     * Texto generado por el modelo, o null si la IA no está disponible, tardó de más o falló.
     *
     * @param  float  $timeout  segundos máximos de espera (Bladebot ~15 s; tareas diferidas pueden esperar más)
     */
    public function complete(string $prompt, int $maxTokens = 120, float $timeout = 15.0, float $temperature = 0.4): ?string
    {
        if (! $this->available()) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout($timeout)
                ->acceptJson()
                ->post(rtrim((string) config('chatbot.ai.ollama.url'), '/').'/api/generate', [
                    'model' => (string) config('chatbot.ai.ollama.model', 'qwen2.5:0.5b'),
                    'prompt' => $prompt,
                    'stream' => false,
                    'keep_alive' => (string) config('chatbot.ai.ollama.keep_alive', '30m'),
                    'options' => [
                        'temperature' => $temperature,
                        'num_predict' => $maxTokens,
                    ],
                ]);

            if ($response->failed()) {
                $this->trip('HTTP '.$response->status());

                return null;
            }

            $text = trim((string) $response->json('response', ''));

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            $this->trip($e->getMessage());

            return null;
        }
    }

    /** Abre el cortacircuito: durante unos minutos nadie espera a una IA que no está respondiendo. */
    private function trip(string $reason): void
    {
        Cache::put(self::DOWN_KEY, true, (int) config('chatbot.ai.ollama.cooldown_seconds', 120));
        Log::warning('IA local no disponible; se usan las respuestas sin IA por unos minutos.', ['motivo' => $reason]);
    }
}
