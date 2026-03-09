<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Service;
use App\Models\Barber;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\GeminiService;

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
        ]
    ];

    public function __construct(private GeminiService $aiService)
    {}

    public function query(Request $request): JsonResponse
    {
        $message = strtolower($request->input('message', ''));
        $user = auth()->user();

        // 1. Recopilar Contexto Real (RAG Lite)
        $contextData = $this->gatherContext($user);

        // 2. Intentar respuesta con IA
        if (config('services.gemini.api_key')) {
            $aiResponse = $this->aiService->generateResponse($message, $contextData);
            // Si la IA responde algo válido (no error), lo usamos
            if (!str_contains($aiResponse, 'MODO OFFLINE') && !str_contains($aiResponse, 'Error de conexión')) {
                return response()->json(['response' => $aiResponse]);
            }
        }

        // 3. Fallback: Lógica Manual (Si no hay IA o falla)
        $manualResponse = $this->manualLogic($message, $user, $contextData);
        
        return response()->json([
            'response' => $manualResponse
        ]);
    }

    private function gatherContext($user): array
    {
        // Servicios
        $services = Service::where('activo', true)->get()->map(fn($s) => "{$s->nombre} (\${$s->precio})")->toArray();
        
        // Barberos
        $barbers = Barber::with('user')->where('activo', true)->get()->pluck('user.name')->toArray();

        // Contexto específico del usuario
        $extraContext = "";
        if ($user) {
            if ($user->hasRole('cliente') && $user->clientProfile) {
                $nextAppt = Appointment::where('client_id', $user->clientProfile->id)
                    ->where('fecha', '>=', now()->toDateString())
                    ->where('estado', '!=', 'cancelada')
                    ->orderBy('fecha')->first();
                
                if ($nextAppt) {
                    $fecha = Carbon::parse($nextAppt->fecha)->translatedFormat('l j de F');
                    $extraContext = "El usuario TIENE una cita programada para el {$fecha} a las {$nextAppt->hora_inicio} con {$nextAppt->barber?->user?->name}.";
                } else {
                    $extraContext = "El usuario NO tiene citas futuras.";
                }
            } elseif ($user->hasRole('administrador')) {
                $total = Payment::whereDate('created_at', now())->sum(DB::raw('monto + propina'));
                $extraContext = "El usuario es ADMIN. La caja de hoy es: $" . number_format($total, 2);
            }
        }

        return [
            'services' => $services,
            'barbers' => $barbers,
            'user_name' => $user?->name,
            'user_role' => $user?->roles->first()?->name ?? 'Visitante',
            'extra_context' => $extraContext
        ];
    }

    private function manualLogic($message, $user, $data)
    {
        // Reutilizamos la lógica robusta anterior como respaldo
        if (str_contains($message, 'servicio') || str_contains($message, 'precio')) {
            return "Ofrecemos: " . implode(', ', array_slice($data['services'], 0, 3)) . "... Ver más en la sección Servicios.";
        }
        if (str_contains($message, 'cita') && str_contains($data['extra_context'], 'TIENE')) {
            return "Recordatorio: " . $data['extra_context'];
        }
        
        foreach ($this->fallbackKnowledgeBase as $category => $items) {
            foreach ($items as $key => $answer) {
                if (str_contains($message, $key)) return $answer;
            }
        }

        return "Como asistente virtual (Modo Básico), no entendí eso. Por favor contacta a recepción o configura mi API Key para activarme al 100%.";
    }
}
