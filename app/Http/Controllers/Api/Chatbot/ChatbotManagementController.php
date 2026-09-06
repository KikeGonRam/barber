<?php

namespace App\Http\Controllers\Api\Chatbot;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\ChatbotContextService;
use App\Services\Chatbot\ChatbotLearningService;
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
        private readonly ChatbotUserProfileService $profileService,
        private readonly ChatbotLearningService $learningService,
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
        $userId = (string) auth()->id();
        $profile = $this->profileService->getUserProfile($userId);
        // Igual que getHistory(): lee el resumen persistido en Mongo, no el
        // de sesión — este endpoint solo lo consume un cliente Bearer-token
        // (Nuxt) que nunca comparte sesión entre requests.
        $summary = $this->contextService->getPersistedSummary($userId);

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
        $stats = $this->learningService->getLearningReport($userId);
        $topCategories = $this->learningService->getTopCategories($userId);

        return response()->json([
            'stats' => $stats,
            'top_categories' => $topCategories,
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
        $validated = $request->validate([
            'history_count' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $historyCount = (int) ($validated['history_count'] ?? 20);

        $result = $this->learningService->trainFromHistory($userId, $historyCount);

        return response()->json([
            'message' => 'Sistema entrenado exitosamente desde el historial.',
            'result' => $result,
        ]);
    }

    /**
     * Feedback de una Respuesta (API)
     *
     * Marca una respuesta puntual del chatbot como útil o no. Antes de
     * este endpoint, ChatbotController::query() llamaba a
     * recordFeedback() con $wasHelpful hardcodeado en `true` en cada una
     * de sus 5 ramas de respuesta — el sistema de aprendizaje nunca había
     * recibido una señal negativa real de ningún usuario. Este endpoint le
     * da al frontend (el widget de chat en Nuxt) una forma de mandar la
     * señal real.
     */
    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'response' => ['required', 'string', 'max:5000'],
            'helpful' => ['required', 'boolean'],
        ]);

        $this->learningService->recordFeedback(
            $validated['message'],
            $validated['response'],
            $validated['helpful'],
            (string) auth()->id(),
        );

        return response()->json([
            'message' => 'Gracias por tu retroalimentación.',
        ]);
    }
}
