<?php

namespace App\Http\Controllers\Chatbot;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Services\Business\BusinessEventService;
use App\Services\Chatbot\ChatbotContextService;
use App\Services\Chatbot\ChatbotExternalDataService;
use App\Services\Chatbot\ChatbotIntelligenceService;
use App\Services\Chatbot\ChatbotLearningService;
use App\Services\Chatbot\ChatbotUserProfileService;
use App\Services\Chatbot\Contracts\ChatbotAiProvider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ChatbotController extends Controller
{
    // Fallback manual knowledge base
    private array $fallbackKnowledgeBase = [
        'sistema' => [
            'puntos' => 'Cada cita completada te otorga 10 Puntos de Estilo. Puedes verlos en tu Dashboard.',
            'membresia' => 'Nuestros niveles son: Caballero, V.I.P y Leyenda.',
            'cancelacion' => 'Puedes cancelar hasta 24 horas antes sin cargo.',
            'pago' => 'Aceptamos Efectivo, Tarjeta, Transferencia y QR.',
        ],
        'general' => [
            'ubicacion' => 'Estamos en Av. Reforma 123, CDMX.',
            'horario' => 'Lunes a Sábado de 9AM a 9PM.',
        ],
    ];

    public function __construct(
        private ChatbotAiProvider $aiService,
        private ChatbotIntelligenceService $intelligenceService,
        private ChatbotExternalDataService $externalDataService,
        private ChatbotContextService $contextService,
        private ChatbotUserProfileService $profileService,
        private ChatbotLearningService $learningService,
        private BusinessEventService $businessEventService
    ) {}

    public function query(Request $request): JsonResponse
    {
        $requestStartedAt = hrtime(true);
        $message = strtolower(trim((string) $request->input('message', '')));
        $user = auth()->user();
        $userId = $user?->id;

        if ($message === '') {
            return response()->json([
                'response' => 'Por favor, escribe una consulta para poder ayudarte.',
            ], 422);
        }

        $rateLimitKey = $userId ? "chatbot:user:{$userId}" : 'chatbot:ip:'.$request->ip();
        $maxAttempts = (int) config('chatbot.rate_limit.max_attempts', 20);
        $decaySeconds = (int) config('chatbot.rate_limit.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            $this->businessEventService->record('chatbot', 'chatbot_rate_limited', [
                'user_id' => $userId,
                'ip' => $request->ip(),
                'retry_after_seconds' => $retryAfter,
            ]);

            $this->recordProviderTelemetry($userId, 'rate_limit', 'blocked', $requestStartedAt, [
                'retry_after_seconds' => $retryAfter,
            ]);

            return response()->json([
                'response' => 'Has alcanzado el limite temporal de consultas. Intenta de nuevo en unos segundos.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, $decaySeconds);

        // 0. APRENDIZAJE AUTOMÁTICO - Detectar intención real
        $realIntent = $this->learningService->detectRealIntent($message);
        $this->learningService->learnQuestion($message, $realIntent, 0.9);

        // GUARDAR CONTEXTO DEL USUARIO
        $this->profileService->updateTopics($message, $userId);

        // 1. PRIORIDAD: Historial local (MÁS RÁPIDO, CONSISTENTE)
        $similarQuestions = $this->contextService->findSimilarQuestions($message, $userId, 70);
        if (! empty($similarQuestions) && $similarQuestions[0]['similarity'] > 80) {
            $response = $similarQuestions[0]['answer'];
            $this->contextService->addMessage($message, $response, 'bot', $userId);
            $this->learningService->recordFeedback($message, $response, true, $userId);

            $this->recordProviderTelemetry($userId, 'memory', 'success', $requestStartedAt, [
                'similarity' => $similarQuestions[0]['similarity'],
            ]);

            return response()->json(['response' => $response]);
        }

        // 2. SEGUNDO: Respuesta Inteligente de BD (Local, contextual)
        $intelligentResponse = null;

        try {
            $intelligentResponse = $this->intelligenceService->getContextualResponse($message, $user);
        } catch (\Throwable $exception) {
            $this->businessEventService->record('chatbot', 'chatbot_intelligence_error', [
                'message' => $message,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            $this->recordProviderTelemetry($userId, 'intelligence', 'error', $requestStartedAt, [
                'error' => $exception->getMessage(),
            ]);

            Log::warning('Chatbot intelligence fallback: '.$exception->getMessage());
        }

        if ($intelligentResponse) {
            $this->contextService->addMessage($message, $intelligentResponse, 'bot', $userId);
            $this->profileService->updateIntent($realIntent, $userId);
            $this->learningService->recordFeedback($message, $intelligentResponse, true, $userId);

            $this->recordProviderTelemetry($userId, 'intelligence', 'success', $requestStartedAt);

            return response()->json(['response' => $intelligentResponse]);
        }

        // 3. TERCERO: Lógica Manual (Rápida, confiable, sin API externa)
        $contextData = $this->gatherContext($user);
        $manualResponse = $this->manualLogic($message, $user, $contextData);

        // Solo si es respuesta genérica, intentar datos externos
        if ($this->isManualFallbackResponse($manualResponse)) {
            // 4. FOURTH: Datos Externos (Wikipedia, OSM) - Solo si nada más funciona
            $externalResponse = null;

            try {
                $externalResponse = $this->externalDataService->getExternalResponse($message);
            } catch (\Throwable $exception) {
                $this->businessEventService->record('chatbot', 'chatbot_external_data_error', [
                    'message' => $message,
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);

                $this->recordProviderTelemetry($userId, 'external_data', 'error', $requestStartedAt, [
                    'error' => $exception->getMessage(),
                ]);

                Log::warning('Chatbot external data fallback: '.$exception->getMessage());
            }

            if ($externalResponse) {
                $this->contextService->addMessage($message, $externalResponse, 'bot', $userId);
                $this->profileService->updateIntent($realIntent, $userId);
                $this->learningService->recordFeedback($message, $externalResponse, true, $userId);

                $this->recordProviderTelemetry($userId, 'external_data', 'success', $requestStartedAt);

                return response()->json(['response' => $externalResponse]);
            }

            // 5. LAST: IA (Ollama local o Gemini nube) con contexto aumentado
            $aiProvider = config('chatbot.ai.provider', 'gemini');
            if ($this->aiService->isEnabled()) {
                try {
                    $basePrompt = $this->aiService->buildSystemPrompt($contextData);
                    $augmentedPrompt = $this->contextService->generateAugmentedPrompt($message, $basePrompt, $userId);
                    $aiResponse = $this->aiService->generateResponseWithPrompt($augmentedPrompt);

                    if ($aiResponse &&
                        ! str_contains($aiResponse, 'MODO OFFLINE') &&
                        ! str_contains($aiResponse, 'interferencias')) {
                        $this->contextService->addMessage($message, $aiResponse, 'bot', $userId);
                        $this->profileService->updateIntent($realIntent, $userId);
                        $this->learningService->recordFeedback($message, $aiResponse, true, $userId);

                        $promptTokens = $this->estimateTokenCount($augmentedPrompt);
                        $responseTokens = $this->estimateTokenCount($aiResponse);
                        $totalTokens = $promptTokens + $responseTokens;

                        $this->recordProviderTelemetry($userId, $aiProvider, 'success', $requestStartedAt, [
                            'model' => $this->aiService->label(),
                            'input_tokens_estimate' => $promptTokens,
                            'output_tokens_estimate' => $responseTokens,
                            'total_tokens_estimate' => $totalTokens,
                            'estimated_cost_usd' => $this->estimateAiCostUsd($totalTokens),
                        ]);

                        return response()->json(['response' => $aiResponse]);
                    }
                } catch (\Exception $e) {
                    $this->businessEventService->record('chatbot', 'chatbot_ai_error', [
                        'message' => $message,
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);

                    $this->recordProviderTelemetry($userId, $aiProvider, 'error', $requestStartedAt, [
                        'error' => $e->getMessage(),
                    ]);

                    \Log::warning('Chatbot AI fallback: '.$e->getMessage());
                }
            }
        }

        // Guardar respuesta manual en contexto
        if ($this->isManualFallbackResponse($manualResponse)) {
            $this->businessEventService->record('chatbot', 'chatbot_fallback', [
                'message' => $message,
                'user_id' => $userId,
                'intent' => $realIntent,
            ]);

            $this->recordProviderTelemetry($userId, 'manual', 'fallback', $requestStartedAt, [
                'intent' => $realIntent,
            ]);
        } else {
            $this->recordProviderTelemetry($userId, 'manual', 'success', $requestStartedAt, [
                'intent' => $realIntent,
            ]);
        }

        $this->contextService->addMessage($message, $manualResponse, 'bot', $userId);
        $this->profileService->updateIntent($realIntent, $userId);
        $this->learningService->recordFeedback($message, $manualResponse, true, $userId);

        return response()->json(['response' => $manualResponse]);
    }

    /**
     * Endpoint para obtener historial de conversación
     */
    public function getHistory(): JsonResponse
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
     * Endpoint para limpiar historial
     */
    public function clearHistory(): JsonResponse
    {
        $userId = auth()->id();
        $this->contextService->clearHistory($userId);
        $this->profileService->clearProfile($userId);

        return response()->json(['message' => 'Historial y perfil limpiados']);
    }

    /**
     * Endpoint para obtener perfil del usuario
     */
    public function getProfile(): JsonResponse
    {
        $userId = auth()->id();
        $profile = $this->profileService->getUserProfile($userId);
        $summary = $this->profileService->getProfileSummary($userId);

        return response()->json([
            'profile' => $profile,
            'summary' => $summary,
        ]);
    }

    private function gatherContext($user): array
    {
        // Servicios
        $services = Service::where('activo', true)->get()->map(fn ($s) => "{$s->nombre} (\${$s->precio})")->toArray();

        // Barberos
        $barbers = Barber::with('user')->where('activo', true)->get()->pluck('user.name')->toArray();

        // Contexto específico del usuario
        $extraContext = '';
        if ($user) {
            if ($user->hasRole('cliente') && $user->clientProfile) {
                $nextAppt = Appointment::where('client_id', (string) $user->clientProfile->id)
                    ->where('fecha', '>=', now()->toDateString())
                    ->where('estado', '!=', 'cancelada')
                    ->orderBy('fecha')->first();

                if ($nextAppt) {
                    $fecha = Carbon::parse($nextAppt->fecha)->translatedFormat('l j de F');
                    $extraContext = "El usuario TIENE una cita programada para el {$fecha} a las {$nextAppt->hora_inicio} con {$nextAppt->barber?->user?->name}.";
                } else {
                    $extraContext = 'El usuario NO tiene citas futuras.';
                }
            } elseif ($user->hasRole('administrador')) {
                $total = Payment::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->get(['monto', 'propina'])->sum(fn($p) => (float)$p->monto + (float)$p->propina);
                $extraContext = 'El usuario es ADMIN. La caja de hoy es: $'.number_format($total, 2);
            }
        }

        return [
            'services' => $services,
            'barbers' => $barbers,
            'user_name' => $user?->name,
            'user_role' => $user?->roles->first()?->name ?? 'Visitante',
            'extra_context' => $extraContext,
        ];
    }

    private function manualLogic($message, $user, $data)
    {
        // BASE DE CONOCIMIENTO EXPANDIDA - RESPUESTAS POR CATEGORÍA

        // ============ PREGUNTAS SOBRE SERVICIOS ============
        if ($this->matchesKeywords($message, ['servicio', 'qué ofrece', 'qué hace', 'precio', 'costo', 'tarifa', 'cuánto cuesta'])) {
            if (empty($data['services'])) {
                return 'Contamos con una amplia gama de servicios de barbería premium incluyendo cortes, afeitados, tratamientos capilares y más. Visita la sección Servicios para ver detalles y precios.';
            }
            $servicesList = implode("\n• ", $data['services']);

            return "Nuestros servicios disponibles:\n• {$servicesList}\n\n¿Quieres reservar alguno?";
        }

        // ============ PREGUNTAS SOBRE CITAS ============
        if ($this->matchesKeywords($message, ['cita', 'reserva', 'agendar', 'cuando', 'disponible', 'horarios disponibles'])) {
            if ($user && $user->hasRole('cliente')) {
                if (str_contains($data['extra_context'], 'TIENE')) {
                    return "{$data['extra_context']}\n\n¿Quieres hacer otra reserva o modificar esta cita?";
                }

                return "Tienes disponible agendar nuevas citas. Ingresa a la sección 'Citas' en tu Dashboard para ver disponibilidad y reservar con tus barberos favoritos.";
            }

            return "Para agendar una cita:\n1. Regístrate o inicia sesión\n2. Ve a la sección 'Citas'\n3. Selecciona barbero, servicio y horario\n4. Confirma tu reserva\n\n¡Te esperamos!";
        }

        // ============ PREGUNTAS SOBRE BARBEROS/PROFESIONALES ============
        if ($this->matchesKeywords($message, ['barbero', 'estilista', 'maestro', 'quién atiende', 'profesional', 'barber'])) {
            if (empty($data['barbers'])) {
                return 'Contamos con un equipo de maestros barberos expertos y certificados, especializados en cortes modernos y tradicionales.';
            }
            $barbersList = implode(', ', $data['barbers']);

            return "Nuestros maestros barberos: {$barbersList}\n\nCada uno especializado en diferentes estilos. ¿Prefieres alguno en particular?";
        }

        // ============ PREGUNTAS SOBRE HORARIOS ============
        if ($this->matchesKeywords($message, ['horario', 'abierto', 'cierre', 'qué hora', 'cuándo atienden', 'hora de apertura'])) {
            return "HORARIOS DE ATENCIÓN:\nLunes a Sábado: 9:00 AM - 9:00 PM\nDomingos: Cerrado\n\n¿Necesitas agendar algo?";
        }

        // ============ PREGUNTAS SOBRE UBICACIÓN/CONTACTO ============
        if ($this->matchesKeywords($message, ['ubicación', 'dirección', 'dónde están', 'cómo llego', 'google maps', 'contacto', 'teléfono'])) {
            return "UBICACIÓN:\nAv. Reforma 123, CDMX\n\nTeléfono: Disponible en la sección Contacto\nEmail: info@barberpro.com\n\n¿Necesitas indicaciones?";
        }

        // ============ PREGUNTAS SOBRE CANCELACIÓN/REEMBOLSO ============
        if ($this->matchesKeywords($message, ['cancelar', 'cancelación', 'reembolso', 'devolver', 'anular cita', 'cambiar cita'])) {
            return "POLÍTICA DE CANCELACIÓN:\n— Hasta 24h antes: GRATIS (sin penalización)\n— Menos de 24h: Se aplica cargo del 50%\n— Sin aviso previo: Cargo del 100%\n\n¿Necesitas cancelar tu cita?";
        }

        // ============ PREGUNTAS SOBRE PAGOS ============
        if ($this->matchesKeywords($message, ['pago', 'tarjeta', 'efectivo', 'transferencia', 'qr', 'cómo pago', 'métodos de pago'])) {
            return "MÉTODOS DE PAGO ACEPTADOS:\n— Efectivo\n— Tarjeta de Crédito/Débito\n— Transferencia Bancaria\n— Código QR (Mercado Pago, OXXO Pay, etc.)\n\nTodos los métodos disponibles en sucursal y en línea.";
        }

        // ============ PREGUNTAS SOBRE PUNTOS/MEMBRESÍA ============
        if ($this->matchesKeywords($message, ['punto', 'miembro', 'membresia', 'nivel', 'caballero', 'vip', 'leyenda', 'recompensa', 'beneficio'])) {
            return "SISTEMA DE PUNTOS 'ESTILO':\nGanas 10 Puntos por cada cita completada\n\nNIVELES DE MEMBRESÍA:\n— CABALLERO (0-50 puntos): Acceso básico\n— V.I.P (51-150 puntos): Descuentos + Prioridad en reservas\n— LEYENDA (150+ puntos): Beneficios exclusivos + Sorpresas especiales\n\n¿Cuál es tu nivel actual?";
        }

        // ============ PREGUNTAS PARA ADMINISTRADORES ============
        if ($user && $user->hasRole('administrador')) {
            if ($this->matchesKeywords($message, ['caja', 'ventas', 'hoy', 'dinero', 'ingresos', 'reporte'])) {
                $extraInfo = $data['extra_context'] ?? 'Accede al Dashboard para ver métricas';

                return "INFORMACIÓN ADMIN:\n{$extraInfo}\n\nPuedes ver reportes detallados en la sección 'Reportes' del Dashboard.";
            }
            if ($this->matchesKeywords($message, ['permiso', 'usuario', 'rol', 'acceso'])) {
                return "GESTIÓN DE USUARIOS:\nDesde 'Usuarios' puedes:\n— Crear y editar usuarios\n— Asignar roles (Cliente, Barbero, Admin)\n— Gestionar permisos específicos\n\n¿Necesitas ayuda con algún usuario?";
            }
        }

        // ============ PREGUNTAS FRECUENTES GENERALES ============
        if ($this->matchesKeywords($message, ['primer', 'primera vez', 'nuevo', 'cómo funciona', 'ayuda', 'tutorial'])) {
            return "BIENVENIDO A URBANBLADE:\n\n1. Regístrate con tu correo\n2. Completa tu perfil\n3. Explora nuestros servicios\n4. Reserva tu cita\n5. Gana puntos y sube de nivel\n\n¿Necesitas ayuda con algo específico?";
        }

        if ($this->matchesKeywords($message, ['promoción', 'oferta', 'descuento', 'ofrecen'])) {
            return "PROMOCIONES:\nEstas y muchas más promociones en el Dashboard.\n¡Seguinos en redes sociales para enterarte de las últimas ofertas!";
        }

        if ($this->matchesKeywords($message, ['producto', 'vender', 'comprar', 'inventario'])) {
            $products = Product::where('activo', true)->count();
            $productsInfo = $products > 0 ? "Tenemos {$products} productos disponibles" : 'Consulta nuestro catálogo de productos';

            return "PRODUCTOS:\n{$productsInfo}. Visita la sección 'Tienda' para ver detalles, precios y características.";
        }

        if ($this->matchesKeywords($message, ['reseña', 'opinión', 'comentario', 'valoración', 'rating'])) {
            return "OPINIONES Y RESEÑAS:\nTus comentarios nos ayudan a mejorar. Después de tu cita, comparte tu experiencia y ayuda a otros clientes a conocer nuestro servicio.";
        }

        // ============ RESPUESTAS CONTEXTUALES POR ROL ============
        if ($user) {
            $role = $user->roles->first()?->name ?? 'usuario';
            if (str_contains($message, 'hola') || str_contains($message, 'buenos') || str_contains($message, 'hi')) {
                return "¡Hola {$user->name}! Soy el Concierge de UrbanBlade. ¿En qué puedo ayudarte hoy?";
            }
        }

        // ============ FALLBACK FINAL ============
        return "No estoy seguro sobre eso. Puedo ayudarte con:\n\n— Servicios y precios\n— Agendar citas\n— Conocer nuestro equipo\n— Horarios y ubicación\n— Métodos de pago\n— Puntos y membresía\n\n¿Hay algo de eso en lo que pueda ayudarte?";
    }

    /**
     * Busca múltiples palabras clave en el mensaje
     */
    public function getLearningStats(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $stats = $this->learningService->getLearningReport($userId);
        $topCategories = $this->learningService->getTopCategories($userId);

        return response()->json([
            'stats' => $stats,
            'top_categories' => $topCategories,
            'message' => 'Learning intelligence report generated',
        ]);
    }

    public function trainFromHistory(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $historyCount = $request->input('history_count', 20);

        // Entrenar desde el historial existente
        $report = $this->learningService->trainFromHistory($userId, $historyCount);

        return response()->json([
            'message' => 'Training from history completed',
            'report' => $report,
        ]);
    }

    /**
     * Busca múltiples palabras clave en el mensaje.
     */
    private function matchesKeywords(string $message, array $keywords): bool
    {
        $normalizedMessage = strtolower($message);

        foreach ($keywords as $keyword) {
            if (str_contains($normalizedMessage, strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    private function isManualFallbackResponse(string $response): bool
    {
        $normalizedResponse = strtolower($response);

        return str_contains($normalizedResponse, 'no estoy seguro')
            || str_contains($normalizedResponse, 'no estoy completamente seguro');
    }

    private function recordProviderTelemetry(mixed $userId, string $source, string $status, int $requestStartedAt, array $extraProperties = []): void
    {
        if (! (bool) config('chatbot.telemetry.enabled', true)) {
            return;
        }

        $sampleRate = (float) config('chatbot.telemetry.sample_rate', 1.0);
        $sampleRate = max(0.0, min(1.0, $sampleRate));

        if ($sampleRate < 1.0 && (mt_rand() / mt_getrandmax()) > $sampleRate) {
            return;
        }

        $latencyMs = max(0, (int) round((hrtime(true) - $requestStartedAt) / 1_000_000));

        $this->businessEventService->record('chatbot', 'chatbot_provider_telemetry', array_merge([
            'user_id' => $userId,
            'source' => $source,
            'status' => $status,
            'latency_ms' => $latencyMs,
        ], $extraProperties));
    }

    private function estimateTokenCount(string $text): int
    {
        return (int) ceil(max(strlen(trim($text)), 1) / 4);
    }

    private function estimateAiCostUsd(int $totalTokens): float
    {
        $costPer1kTokens = (float) config('chatbot.telemetry.ai_cost_per_1k_tokens', 0.0);

        if ($costPer1kTokens <= 0) {
            return 0.0;
        }

        return round(($totalTokens / 1000) * $costPer1kTokens, 6);
    }
}
