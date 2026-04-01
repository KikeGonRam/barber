<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ChatbotUserProfileService
{
    /**
     * Obtiene o crea el perfil de conversación del usuario
     */
    public function getUserProfile($userId = null): array
    {
        $key = $this->getProfileKey($userId);

        $profile = Cache::get($key, [
            'user_id' => $userId,
            'conversation_style' => 'professional_friendly',
            'topics_discussed' => [],
            'last_intent' => null,
            'context_strength' => 'high', // high, medium, low
            'response_tone' => 'professional',
            'preferences' => [],
            'created_at' => now()->toIso8601String(),
            'last_updated' => now()->toIso8601String(),
        ]);

        return $profile;
    }

    /**
     * Guarda el perfil del usuario (cache de 7 días)
     */
    public function saveUserProfile($profile, $userId = null): void
    {
        $key = $this->getProfileKey($userId);
        $profile['last_updated'] = now()->toIso8601String();
        Cache::put($key, $profile, 86400 * 7); // 7 días
    }

    /**
     * Actualiza tópicos de la conversación
     */
    public function updateTopics(string $keyword, $userId = null): array
    {
        $profile = $this->getUserProfile($userId);

        if (! in_array($keyword, $profile['topics_discussed'])) {
            $profile['topics_discussed'][] = $keyword;
            // Limitar a últimos 10 tópicos
            if (count($profile['topics_discussed']) > 10) {
                array_shift($profile['topics_discussed']);
            }
        }

        $this->saveUserProfile($profile, $userId);

        return $profile;
    }

    /**
     * Actualiza la intención del usuario
     */
    public function updateIntent(string $intent, $userId = null): void
    {
        $profile = $this->getUserProfile($userId);
        $profile['last_intent'] = $intent;
        $this->saveUserProfile($profile, $userId);
    }

    /**
     * Obtiene resumen del perfil actual
     */
    public function getProfileSummary($userId = null): string
    {
        $profile = $this->getUserProfile($userId);

        $summary = "PERFIL DE USUARIO:\n";
        $summary .= "Estilo: {$profile['conversation_style']}\n";
        $summary .= "Tono: {$profile['response_tone']}\n";

        if (! empty($profile['topics_discussed'])) {
            $summary .= 'Tópicos recientes: '.implode(', ', array_slice($profile['topics_discussed'], -3))."\n";
        }

        if ($profile['last_intent']) {
            $summary .= "Última intención: {$profile['last_intent']}\n";
        }

        return $summary;
    }

    /**
     * Obtiene clave de caché del perfil
     */
    private function getProfileKey($userId = null): string
    {
        $id = $userId ?? auth()->id() ?? 'guest_'.request()->ip();

        return "chatbot_profile_{$id}";
    }

    /**
     * Limpia el perfil del usuario
     */
    public function clearProfile($userId = null): void
    {
        $key = $this->getProfileKey($userId);
        Cache::forget($key);
    }
}
