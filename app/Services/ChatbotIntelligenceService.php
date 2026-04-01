<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;

class ChatbotIntelligenceService
{
    /**
     * Obtiene datos de TODA la BD para contexto inteligente
     */
    public function gatherExtendedContext($user = null): array
    {
        return [
            'trending_barbers' => $this->getTrendingBarbers(),
            'trending_works' => $this->getTrendingWorks(),
            'popular_services' => $this->getPopularServices(),
            'top_comments' => $this->getTopComments(),
            'user_history' => $user ? $this->getUserHistory($user) : [],
            'user_preferences' => $user ? $this->getUserPreferences($user) : [],
            'busy_times' => $this->getBusyTimes(),
            'slow_times' => $this->getSlowTimes(),
            'best_sellers' => $this->getBestSellingProducts(),
            'stats' => $this->getBusinessStats(),
        ];
    }

    /**
     * Barberos más populares (más trabajos, más comentarios, más reseñas)
     */
    private function getTrendingBarbers(): array
    {
        $barbers = Barber::with('user')
            ->where('activo', true)
            ->withCount([
                'works' => fn ($q) => $q->where('created_at', '>=', now()->subMonths(3)),
                'appointments' => fn ($q) => $q->where('estado', 'completada')->where('fecha', '>=', now()->subMonths(3)),
            ])
            ->withAvg('comments', 'rating')
            ->orderByDesc('works_count')
            ->limit(5)
            ->get()
            ->map(function ($barber) {
                return [
                    'name' => $barber->user->name,
                    'works' => $barber->works_count ?? 0,
                    'appointments' => $barber->appointments_count ?? 0,
                    'rating' => round($barber->comments_avg_rating ?? 0, 1),
                    'specialty' => $barber->especialidad ?? 'Barbería general',
                ];
            })
            ->toArray();

        return $barbers;
    }

    /**
     * Trabajos más populares (más likes, más comentarios)
     */
    private function getTrendingWorks(): array
    {
        $works = Work::with('barber.user', 'workImages')
            ->where('created_at', '>=', now()->subMonths(3))
            ->withCount([
                'reactions' => fn ($q) => $q->where('tipo', 'like'),
                'comments',
            ])
            ->orderByDesc('reactions_count')
            ->limit(5)
            ->get()
            ->map(function ($work) {
                return [
                    'title' => $work->titulo,
                    'barber' => $work->barber?->user?->name,
                    'likes' => $work->reactions_count ?? 0,
                    'comments' => $work->comments_count ?? 0,
                    'description' => $work->descripcion,
                ];
            })
            ->toArray();

        return $works;
    }

    /**
     * Servicios más reservados/populares
     */
    private function getPopularServices(): array
    {
        $services = Service::where('activo', true)
            ->withCount([
                'appointments' => fn ($q) => $q->where('estado', 'completada')->where('created_at', '>=', now()->subMonths(3)),
            ])
            ->orderByDesc('appointments_count')
            ->limit(5)
            ->get()
            ->map(function ($service) {
                return [
                    'name' => $service->nombre,
                    'price' => $service->precio,
                    'duration' => $service->duracion_minutos ?? 30,
                    'bookings' => $service->appointments_count ?? 0,
                    'description' => $service->descripcion,
                ];
            })
            ->toArray();

        return $services;
    }

    /**
     * Mejores comentarios y reseñas
     */
    private function getTopComments(): array
    {
        $comments = Comment::where('rating', '>=', 4)
            ->with('user', 'work.barber.user')
            ->orderByDesc('rating')
            ->limit(5)
            ->get()
            ->map(function ($comment) {
                return [
                    'author' => $comment->user?->name,
                    'rating' => $comment->rating,
                    'text' => substr((string) $comment->comment, 0, 100).'...',
                    'barber' => $comment->work?->barber?->user?->name,
                ];
            })
            ->toArray();

        return $comments;
    }

    /**
     * Historial del usuario (citas pasadas, trabajos vistos, favoritos)
     */
    private function getUserHistory($user): array
    {
        $client = $user->clientProfile;

        if (! $client) {
            return [];
        }

        $appointments = Appointment::where('client_id', $client->id)
            ->where('estado', 'completada')
            ->orderByDesc('fecha')
            ->limit(10)
            ->get()
            ->map(fn ($apt) => [
                'barber' => $apt->barber?->user?->name,
                'service' => $apt->service?->nombre,
                'date' => Carbon::parse($apt->fecha)->translatedFormat('l j F'),
                'rating' => $apt->puntuacion,
            ])
            ->toArray();

        $favoriteBarbers = Appointment::where('client_id', $client->id)
            ->where('estado', 'completada')
            ->groupBy('barber_id')
            ->selectRaw('barber_id, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(3)
            ->with('barber.user')
            ->get()
            ->pluck('barber.user.name')
            ->toArray();

        return [
            'past_appointments' => $appointments,
            'favorite_barbers' => $favoriteBarbers,
            'total_citas' => Appointment::where('client_id', $client->id)->where('estado', 'completada')->count(),
        ];
    }

    /**
     * Preferencias del usuario basadas en su historial
     */
    private function getUserPreferences($user): array
    {
        $client = $user->clientProfile;

        if (! $client) {
            return [];
        }

        // Servicio más usado
        $favoriteService = Appointment::where('client_id', $client->id)
            ->where('estado', 'completada')
            ->groupBy('service_id')
            ->selectRaw('service_id, COUNT(*) as count')
            ->orderByDesc('count')
            ->first();

        // Barbero más visitado
        $favoriteBarber = Appointment::where('client_id', $client->id)
            ->where('estado', 'completada')
            ->groupBy('barber_id')
            ->selectRaw('barber_id, COUNT(*) as count')
            ->orderByDesc('count')
            ->first()
            ?->barber?->user?->name;

        // Promedio de gasto
        $avgSpent = Payment::whereHas('appointment', fn ($q) => $q->where('client_id', $client->id))
            ->recent()
            ->average('monto') ?? 0;

        return [
            'favorite_service' => $favoriteService?->service?->nombre,
            'favorite_barber' => $favoriteBarber,
            'average_spending' => round($avgSpent, 2),
            'loyalty_level' => $this->getUserLoyaltyLevel($client),
        ];
    }

    /**
     * Calcula el nivel de lealtad del usuario
     */
    private function getUserLoyaltyLevel($client): string
    {
        $appointmentCount = Appointment::where('client_id', $client->id)
            ->where('estado', 'completada')
            ->count();

        if ($appointmentCount >= 20) {
            return 'VIP';
        }
        if ($appointmentCount >= 10) {
            return 'Frecuente';
        }
        if ($appointmentCount >= 5) {
            return 'Regular';
        }

        return 'Nuevo';
    }

    /**
     * Horarios ocupados (cuándo está lleno)
     */
    private function getBusyTimes(): array
    {
        $busy = Appointment::where('fecha', '>=', now()->toDateString())
            ->where('estado', '!=', 'cancelada')
            ->groupBy('hora_inicio')
            ->selectRaw('hora_inicio, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(3)
            ->get()
            ->map(fn ($apt) => $apt->hora_inicio)
            ->toArray();

        return $busy;
    }

    /**
     * Horarios disponibles (cuándo está libre)
     */
    private function getSlowTimes(): array
    {
        $slowTimes = [];
        for ($hour = 9; $hour <= 20; $hour++) {
            $time = str_pad($hour, 2, '0', STR_PAD_LEFT).':00';
            $count = Appointment::where('hora_inicio', $time)
                ->where('fecha', '>=', now()->toDateString())
                ->where('estado', '!=', 'cancelada')
                ->count();

            if ($count == 0) {
                $slowTimes[] = $time;
            }
        }

        return array_slice($slowTimes, 0, 3);
    }

    /**
     * Productos más vendidos
     */
    private function getBestSellingProducts(): array
    {
        // Nota: Ajusta según tu estructura de tabla de ventas
        $products = Product::where('activo', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->nombre,
                'price' => $p->precio,
                'category' => $p->categoria ?? 'Producto',
            ])
            ->toArray();

        return $products;
    }

    /**
     * Estadísticas del negocio
     */
    private function getBusinessStats(): array
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth();

        $todayRevenue = Payment::whereDate('created_at', $today)->sum('monto');
        $thisMonthRevenue = Payment::where('created_at', '>=', $thisMonth)->sum('monto');
        $todayAppointments = Appointment::whereDate('fecha', $today)
            ->where('estado', '!=', 'cancelada')
            ->count();
        $totalClients = Client::count();

        return [
            'today_revenue' => round($todayRevenue, 2),
            'month_revenue' => round($thisMonthRevenue, 2),
            'today_appointments' => $todayAppointments,
            'total_clients' => $totalClients,
            'active_barbers' => Barber::where('activo', true)->count(),
        ];
    }

    /**
     * Genera recomendaciones personalizadas
     */
    public function getRecommendations($user = null): array
    {
        $recommendations = [];

        if (! $user) {
            return [
                'message' => '¡Bienvenido! Visita nuestro portafolio para ver los trabajos más destacados.',
                'trending' => $this->getTrendingBarbers(),
            ];
        }

        $preferences = $this->getUserPreferences($user);
        $trending = $this->getTrendingWorks();

        if (! empty($preferences['favorite_barber'])) {
            $recommendations[] = "Te recomendamos agendar nuevamente con {$preferences['favorite_barber']}, tu barbero favorito.";
        }

        if (! empty($trending)) {
            $recommendations[] = 'Mira nuestros trabajos trending: '.implode(', ', array_slice(array_map(fn ($w) => $w['title'], $trending), 0, 2));
        }

        if ($preferences['loyalty_level'] === 'VIP') {
            $recommendations[] = 'Como miembro VIP, tienes acceso a descuentos exclusivos. ¡Consulta con recepción!';
        }

        return $recommendations;
    }

    /**
     * Respuesta contextual basada en análisis avanzado
     */
    public function getContextualResponse(string $message, $user = null): ?string
    {
        $message = strtolower($message);
        $extended = $this->gatherExtendedContext($user);

        // TRENDING QUERIES
        if (str_contains($message, 'trending') || str_contains($message, 'popular') || str_contains($message, 'moda')) {
            if (! empty($extended['trending_works'])) {
                $works = array_map(fn ($w) => "{$w['title']} ({$w['likes']} likes)", $extended['trending_works']);

                return "📈 TRABAJOS TRENDING:\n• ".implode("\n• ", $works);
            }
        }

        // BEST BARBERS
        if (str_contains($message, 'mejor barbero') || str_contains($message, 'maestro') || str_contains($message, 'top barber')) {
            if (! empty($extended['trending_barbers'])) {
                $barbers = array_map(fn ($b) => "{$b['name']} ({$b['rating']}⭐)", $extended['trending_barbers']);

                return "🏆 MAESTROS TOP:\n• ".implode("\n• ", $barbers);
            }
        }

        // BUSY VS SLOW TIMES
        if (str_contains($message, 'cuándo disponible') || str_contains($message, 'horario libre')) {
            $slowTimes = $extended['slow_times'];
            if (! empty($slowTimes)) {
                return "✅ HORARIOS DISPONIBLES:\n• ".implode("\n• ", $slowTimes)."\n\nReserva en estos horarios sin esperar.";
            }
        }

        // PERSONALIZED FOR USER
        if ($user && $user->hasRole('cliente')) {
            if (str_contains($message, 'recomendación') || str_contains($message, 'qué debería')) {
                $recs = $this->getRecommendations($user);
                if (! empty($recs)) {
                    return implode("\n\n", $recs);
                }
            }

            if (str_contains($message, 'mi historial') || str_contains($message, 'mis citas')) {
                if (! empty($extended['user_history'])) {
                    $history = $extended['user_history'];
                    $level = $extended['user_preferences']['loyalty_level'] ?? 'Nuevo';
                    $favBarber = $history['favorite_barbers'][0] ?? 'N/A';

                    return "📋 TU PERFIL:\nNivel: {$level}\nCitas realizadas: {$history['total_citas']}\nBarbero favorito: {$favBarber}";
                }
            }
        }

        // ADMIN STATS
        if ($user && $user->hasRole('administrador')) {
            if (str_contains($message, 'estadística') || str_contains($message, 'métricas hoy')) {
                $stats = $extended['stats'];

                return "📊 MÉTRICAS HOY:\n💵 Ingresos: \${$stats['today_revenue']}\n📅 Citas: {$stats['today_appointments']}\n👥 Total de clientes: {$stats['total_clients']}\n💈 Barberos activos: {$stats['active_barbers']}";
            }
        }

        return null;
    }
}
