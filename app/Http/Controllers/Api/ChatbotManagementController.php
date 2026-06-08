<?php

namespace App\Http\Controllers\Api;

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
     */
    public function getHistory(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $history = $this->contextService->getConversationHistory($userId);
        $summary = $this->contextService->getConversationSummary($userId);

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
