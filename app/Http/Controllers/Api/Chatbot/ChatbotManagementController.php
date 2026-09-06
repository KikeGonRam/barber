<?php

namespace App\Http\Controllers\Api\Chatbot;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\ChatbotContextService;
use App\Services\Chatbot\ChatbotUserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Gestión de Chatbot
 *
 * Endpoints para gestionar el chatbot (historial, perfil, estadísticas).
 */
class ChatbotManagementController extends Controller
{
    public function __construct(
        private readonly ChatbotContextService $contextService,
        private readonly ChatbotUserProfileService $profileService
    ) {}

    /**
     * Historial de Conversaciones (API)
     *
     * Devuelve el historial de conversaciones del usuario con el chatbot.
     * A diferencia del widget Blade (sesión), este endpoint lo consume un
     * cliente Bearer-token sin cookie (Nuxt) que nunca conserva sesión
     * entre requests — por eso lee de la copia persistida en Mongo
     * (ChatbotContextService::getPersistedHistory()) en vez de la sesión.
     */
    public function getHistory(Request $request): JsonResponse
    {
        $userId = (string) auth()->id();
        $history = $this->contextService->getPersistedHistory($userId);
        $summary = $this->contextService->getPersistedSummary($userId);

        return response()->json([
            'history' => $history,
            'summary' => $summary,
        ]);
    }

    /**
     * Limpiar Historial (API)
     *
     * Elimina todo el historial de conversaciones del usuario.
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $this->contextService->clearHistory($userId);
        $this->profileService->clearProfile($userId);

        return response()->json([
            'message' => 'Historial de conversaciones eliminado correctamente.',
        ]);
    }

    /**
     * Perfil del Usuario en Chatbot (API)
     *
     * Devuelve el perfil del usuario basado en las interacciones con el chatbot.
     */
    public function getProfile(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $profile = $this->profileService->getUserProfile($userId);
        $summary = $this->contextService->getConversationSummary($userId);

        return response()->json([
            'profile' => $profile,
            'summary' => $summary,
        ]);
    }

    /**
     * Estadísticas de Aprendizaje (API)
     *
     * Devuelve las estadísticas de aprendizaje del chatbot del usuario.
     */
    public function getLearningStats(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $stats = $this->contextService->getUserLearningStats($userId);

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Entrenar desde Historial (API - Solo Admin)
     *
     * Entrena el sistema de aprendizaje desde el historial de conversaciones.
     */
    public function trainFromHistory(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $result = $this->contextService->trainFromHistory($userId);

        return response()->json([
            'message' => 'Sistema entrenado exitosamente desde el historial.',
            'result' => $result,
        ]);
    }
}
