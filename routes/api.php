<?php

use App\Http\Controllers\Api\Admin\Barber\BarberAdminController;
use App\Http\Controllers\Api\Admin\Client\ClientAdminController;
use App\Http\Controllers\Api\Admin\Dashboard\DashboardAdminController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryAdminController;
use App\Http\Controllers\Api\Admin\Membership\MembershipPlanController;
use App\Http\Controllers\Api\Admin\Package\ServicePackageController as AdminServicePackageController;
use App\Http\Controllers\Api\Admin\Report\ReportAdminController;
use App\Http\Controllers\Api\Admin\System\BackupController;
use App\Http\Controllers\Api\Admin\System\SystemController;
use App\Http\Controllers\Api\Analytics\AnalyticsController as ApiAnalyticsController;
use App\Http\Controllers\Api\Appointment\AppointmentController;
use App\Http\Controllers\Api\Appointment\AppointmentManageController;
use App\Http\Controllers\Api\Appointment\AvailabilityController;
use App\Http\Controllers\Api\Appointment\WaitlistController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Barber\BarberManagementController;
use App\Http\Controllers\Api\Barber\BarberPortfolioController;
use App\Http\Controllers\Api\Barber\BarberScheduleController;
use App\Http\Controllers\Api\Campaign\CampaignController as ApiCampaignController;
use App\Http\Controllers\Api\Catalog\CatalogController;
use App\Http\Controllers\Api\Chatbot\ChatbotManagementController;
use App\Http\Controllers\Api\Client\ClientController as ApiClientController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dashboard\MembershipController as ApiMembershipController;
use App\Http\Controllers\Api\Inventory\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\Log\LogController as ApiLogController;
use App\Http\Controllers\Api\Loyalty\ReferralController;
use App\Http\Controllers\Api\Membership\MembershipController as RecurringMembershipController;
use App\Http\Controllers\Api\Notification\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\Order\OrderController as ApiOrderController;
use App\Http\Controllers\Api\Package\GiftCardController;
use App\Http\Controllers\Api\Package\PackagePurchaseController;
use App\Http\Controllers\Api\Payment\CashCloseController;
use App\Http\Controllers\Api\Payment\DepositController;
use App\Http\Controllers\Api\Payment\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\Payment\StripeWebhookController;
use App\Http\Controllers\Api\Prediction\PredictionController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Push\PushController;
use App\Http\Controllers\Api\Raffle\RaffleController as ApiRaffleController;
use App\Http\Controllers\Api\Report\ReportController as ApiReportController;
use App\Http\Controllers\Api\Review\ReviewController as ApiReviewController;
use App\Http\Controllers\Api\Service\ServiceManagementController as ApiServiceManagementController;
use App\Http\Controllers\Api\Setting\SettingController as ApiSettingController;
use App\Http\Controllers\Api\Social\SocialController as ApiSocialController;
use App\Http\Controllers\Api\User\UserController as ApiUserController;
use App\Http\Controllers\Chatbot\ChatbotController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Rutas fuera del prefijo v1: integraciones externas y compatibilidad retro.
// Stripe webhook — sin auth ni CSRF; Stripe valida con firma HMAC
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([PreventRequestForgery::class]);

// Backward compatibility for older frontend cache that still calls /api/availability/slots
Route::get('availability/slots', [AvailabilityController::class, 'slots'])
    ->middleware('throttle:30,1');

// API móvil (app cliente/barbero/admin), versionada bajo /v1. Autenticación por token
// Bearer (middleware 'mobile.auth'), no por sesión web.
Route::prefix('v1')->group(function (): void {
    // Rutas públicas: login, registro, recuperación de contraseña y catálogo (sin token).
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

    // Login social (auth-polish-plan): redirect/callback son GET porque el
    // navegador navega ahí de verdad (pantalla de consentimiento de Google),
    // no son llamadas fetch/JSON como el resto de esta API.
    Route::get('auth/google/redirect', [SocialAuthController::class, 'redirect'])->middleware('throttle:10,1');
    Route::get('auth/google/callback', [SocialAuthController::class, 'callback'])->middleware('throttle:10,1');

    // Catálogo público
    // Ficha del negocio (identidad/contacto/horario/política/redes) sin token:
    // la consumen la landing y la reserva pública. Distinta de GET settings
    // (admin), que además expone datos bancarios.
    Route::get('barbershop', [CatalogController::class, 'barbershop']);
    Route::get('services', [CatalogController::class, 'services']);
    Route::get('barbers', [CatalogController::class, 'barbers']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('availability/slots', [AvailabilityController::class, 'slots'])->middleware('throttle:30,1');

    // Gestión de cita por enlace del recordatorio: sin sesión, autorizada por
    // el token opaco de la propia cita (AppointmentManageLinkService).
    // Throttle bajo a propósito: es el único punto de la API donde un token
    // se puede intentar adivinar, y 10/min por IP hace inviable la búsqueda.
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::get('appointments/{appointment}/manage', [AppointmentManageController::class, 'show']);
        Route::post('appointments/{appointment}/manage/cancel', [AppointmentManageController::class, 'cancel']);
        Route::post('appointments/{appointment}/manage/reschedule', [AppointmentManageController::class, 'reschedule']);
    });
    Route::get('social/feed', [ApiSocialController::class, 'feed'])->middleware('mobile.auth.optional');

    // Chatbot (público con rate limiting; mobile.auth.optional para que, si
    // hay un token válido, el mensaje quede asociado y persistido en Mongo
    // -- ver ChatbotContextService::persistMessage() -- sin exigir sesión a
    // invitados, mismo criterio que social/feed).
    Route::post('chatbot/query', [ChatbotController::class, 'query'])->middleware(['throttle:10,1', 'mobile.auth.optional']);

    // Rutas protegidas (requieren token Bearer): disponibles para cualquier usuario
    // autenticado; los sub-grupos de abajo añaden restricción por rol.
    // "maintenance.check" corre después de "mobile.auth" (ya resuelto
    // $request->user()) y bloquea con 503 a cualquier no-administrador
    // cuando el modo mantenimiento está activo -- equivalente de
    // CheckMaintenanceMode (Blade) para la API.
    Route::middleware(['mobile.auth', 'maintenance.check'])->group(function (): void {
        // Autenticación
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh-token', [AuthController::class, 'refreshToken']);

        // Gestión de Perfil
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('profile/push-token', [ProfileController::class, 'savePushToken']);
        Route::delete('profile', [ProfileController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Tarjeta de membresia (PDF descargable, solo cliente -- 403 en el
        // controlador para quien no tenga clientProfile).
        Route::get('dashboard/membership/card', [ApiMembershipController::class, 'downloadCard']);

        // Analítica (los 4 roles la ven, cada uno con su propio recorte — ver AnalyticsController)
        Route::get('analytics', [ApiAnalyticsController::class, 'index']);

        // Citas
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::get('appointments/calendar-data', [AppointmentController::class, 'calendarData']);
        Route::get('appointments/chargeable', [AppointmentController::class, 'chargeable']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);

        // Lista de espera: cliente se anota/consulta/cancela la suya; staff
        // consulta con filtros por barbero/fecha (branching por rol dentro
        // del controlador, mismo criterio que appointments.index()).
        Route::get('waitlist', [WaitlistController::class, 'index']);
        Route::post('waitlist', [WaitlistController::class, 'store']);
        Route::delete('waitlist/{waitlist}', [WaitlistController::class, 'destroy']);

        // Referidos: solo cliente (mi código propio + a quién he referido).
        Route::get('referrals/mine', [ReferralController::class, 'mine']);
        Route::post('referrals/link', [ReferralController::class, 'link']);

        // Pedidos (cliente ve/crea/cancela los suyos; admin/recepción ven y
        // gestionan todos — branching por rol dentro del controlador, mismo
        // criterio que appointments.index()).
        Route::get('orders', [ApiOrderController::class, 'index']);
        Route::post('orders', [ApiOrderController::class, 'store']);
        Route::patch('orders/{order}/cancel', [ApiOrderController::class, 'cancel']);

        // Pagos/facturas propias (cliente ve solo lo suyo; admin/recepción ven
        // todo — branching por rol dentro del controlador, mismo criterio que
        // appointments.index()/orders.index()).
        Route::get('payments', [ApiPaymentController::class, 'index']);
        Route::get('payments/{payment}/receipt', [ApiPaymentController::class, 'receipt']);

        // Autopago del cliente (Fase B del plan Stripe, 2026-09-06): abierta a
        // cualquier usuario autenticado a nivel de ruta a propósito -- el
        // controlador (authorizeStaffOrOwningClient()) es quien de verdad
        // exige "eres staff, o eres el cliente dueño de esta cita concreta".
        // No puede vivir dentro del grupo role.custom:administrador,
        // recepcionista de abajo porque eso bloquearia a cliente antes de
        // que el controlador tenga oportunidad de validar la propiedad.
        Route::post('payments/stripe-intent', [ApiPaymentController::class, 'stripeIntent'])->name('api.payments.stripe-intent');

        // Depósito anti-no-show de una cita propia (DepositController exige
        // dueño de la cita, mismo patrón que stripe-intent de arriba).
        Route::post('appointments/{appointment}/deposit/stripe-intent', [DepositController::class, 'stripeIntent']);
        Route::post('appointments/{appointment}/deposit/receipt', [DepositController::class, 'uploadReceipt']);

        // Paquetes prepagados: catálogo y "mis paquetes" abiertos a cualquier
        // autenticado (cliente ve lo suyo, staff filtra por client_id) --
        // branching por rol dentro del controlador, mismo criterio que
        // appointments.index(). Vender uno nuevo (store) sí es solo staff,
        // ver el grupo de abajo.
        Route::get('packages/catalog', [PackagePurchaseController::class, 'catalog']);
        Route::get('packages', [PackagePurchaseController::class, 'index']);
        Route::post('packages/stripe-intent', [PackagePurchaseController::class, 'stripeIntent']);

        // Gift cards: consultar saldo por código y comprar con tarjeta,
        // abiertos a cualquier autenticado (una gift card no "pertenece" a
        // nadie, quien tenga el código la puede consultar/usar). Venta en
        // efectivo sí es solo staff, ver el grupo de abajo.
        // 'mine' antes de '{code}' -- si no, el comodín lo tragaría como
        // si fuera un código de tarjeta.
        Route::get('gift-cards/mine', [GiftCardController::class, 'mine']);
        Route::get('gift-cards/{code}', [GiftCardController::class, 'show']);
        Route::post('gift-cards/stripe-intent', [GiftCardController::class, 'stripeIntent']);

        // Membresía recurrente (roadmap P1, Stripe Subscriptions): catálogo de
        // planes abierto a cualquier autenticado; contratar/consultar/cancelar
        // la propia son solo cliente (el controlador ya lo exige).
        Route::get('memberships/plans', [RecurringMembershipController::class, 'plans']);
        Route::get('memberships/mine', [RecurringMembershipController::class, 'mine']);
        Route::post('memberships/subscribe', [RecurringMembershipController::class, 'subscribe']);
        Route::post('memberships/cancel', [RecurringMembershipController::class, 'cancel']);

        // Solo administrador y recepcionista: gestión de pagos, clientes e inventario.
        Route::middleware('role.custom:administrador,recepcionista')->group(function (): void {
            // Pedidos — bandeja de recepción (Admin/Recepcionista)
            Route::patch('orders/{order}/deliver', [ApiOrderController::class, 'deliver']);
            Route::get('orders/{order}/receipt', [ApiOrderController::class, 'receipt']);

            // Corte de caja (Admin/Recepcionista). Antes de 'payments/{payment}'
            // no hay riesgo de comodín porque cuelga de su propio prefijo.
            Route::get('cash-closes', [CashCloseController::class, 'index']);
            Route::get('cash-closes/preview', [CashCloseController::class, 'preview']);
            Route::post('cash-closes', [CashCloseController::class, 'store']);

            // Pagos (Admin/Recepcionista)
            Route::get('payments/pending', [ApiPaymentController::class, 'pending']);
            Route::post('payments', [ApiPaymentController::class, 'store']);
            Route::post('payments/{payment}/approve', [ApiPaymentController::class, 'approve']);
            Route::post('payments/{payment}/reject', [ApiPaymentController::class, 'reject']);
            Route::delete('payments/{payment}', [ApiPaymentController::class, 'destroy']);

            // Depósitos por transferencia pendientes de revisión (Admin/Recepcionista)
            Route::get('deposits/pending', [DepositController::class, 'pending']);
            Route::post('deposits/{payment}/approve', [DepositController::class, 'approve']);
            Route::post('deposits/{payment}/reject', [DepositController::class, 'reject']);

            // Venta de un paquete prepagado en efectivo (Admin/Recepcionista)
            Route::post('packages', [PackagePurchaseController::class, 'store']);

            // Venta de una gift card en efectivo (Admin/Recepcionista)
            Route::post('gift-cards', [GiftCardController::class, 'store']);

            // Plantillas de paquetes prepagados -- solo administrador (el
            // controlador ya lo exige, la ruta solo evita el viaje redondo
            // a un 403 para recepcionista).
            Route::middleware('role.custom:administrador')->group(function (): void {
                Route::get('admin/service-packages', [AdminServicePackageController::class, 'index']);
                Route::post('admin/service-packages', [AdminServicePackageController::class, 'store']);
                Route::put('admin/service-packages/{servicePackage}', [AdminServicePackageController::class, 'update']);
                Route::delete('admin/service-packages/{servicePackage}', [AdminServicePackageController::class, 'destroy']);

                // Planes de membresía recurrente -- solo administrador.
                Route::get('admin/membership-plans', [MembershipPlanController::class, 'index']);
                Route::post('admin/membership-plans', [MembershipPlanController::class, 'store']);
                Route::put('admin/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'update']);
                Route::delete('admin/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'destroy']);
            });

            // Clientes (Admin/Recepcionista)
            Route::get('clients', [ApiClientController::class, 'index']);
            Route::post('clients', [ApiClientController::class, 'store']);
            Route::put('clients/{client}', [ApiClientController::class, 'update']);
            Route::delete('clients/{client}', [ApiClientController::class, 'destroy']);

            // Inventario (Admin/Recepcionista; acciones sensibles se refuerzan en controller)
            Route::get('inventory/products', [ApiInventoryController::class, 'products']);
            Route::post('inventory/products', [ApiInventoryController::class, 'storeProduct']);
            Route::put('inventory/products/{product}', [ApiInventoryController::class, 'updateProduct']);
            Route::delete('inventory/products/{product}', [ApiInventoryController::class, 'destroyProduct']);
            Route::post('inventory/products/{product}/mark-ordered', [ApiInventoryController::class, 'markProductOrdered']);
            Route::get('inventory/low-stock', [ApiInventoryController::class, 'lowStock']);
            Route::get('inventory/movements', [ApiInventoryController::class, 'movements']);
            Route::post('inventory/movements', [ApiInventoryController::class, 'storeMovement']);
        });

        // Solo administrador: servicios, barberos, usuarios, reportes, configuración y logs.
        Route::middleware('role.custom:administrador')->group(function (): void {
            // Servicios (Admin)
            Route::get('services/manage', [ApiServiceManagementController::class, 'index']);
            Route::post('services/manage', [ApiServiceManagementController::class, 'store']);
            Route::put('services/manage/{service}', [ApiServiceManagementController::class, 'update']);
            Route::delete('services/manage/{service}', [ApiServiceManagementController::class, 'destroy']);

            // Barberos (Admin)
            Route::get('barbers/manage', [BarberManagementController::class, 'index']);
            Route::put('barbers/manage/{barber}', [BarberManagementController::class, 'update']);

            // Usuarios (Admin)
            Route::get('users', [ApiUserController::class, 'index']);
            Route::post('users', [ApiUserController::class, 'store']);
            Route::put('users/{user}', [ApiUserController::class, 'update']);
            Route::delete('users/{user}', [ApiUserController::class, 'destroy']);

            // Configuración (Admin)
            Route::get('settings', [ApiSettingController::class, 'show']);
            Route::put('settings', [ApiSettingController::class, 'update']);
            Route::post('settings/maintenance', [ApiSettingController::class, 'toggleMaintenance']);

            // Campañas (Admin)
            Route::get('campaigns', [ApiCampaignController::class, 'index']);
            Route::post('campaigns', [ApiCampaignController::class, 'store']);

            // Sorteos (Admin)
            Route::get('raffles', [ApiRaffleController::class, 'index']);

            // Reseñas de clientes a barberos (Admin)
            Route::get('reviews', [ApiReviewController::class, 'index']);

            // Respaldo de la base de datos (zip, mismo export que la ruta
            // web de Blade -- ver App\Services\System\DatabaseBackupService).
            Route::get('system/backup', [BackupController::class, 'download']);
        });

        // Administrador e ingeniero (rol de solo lectura): reportes y logs.
        // ingeniero nunca gestiona nada de negocio -- ver guardrail #24 en
        // .claude/skills/urbanblade-guardrails antes de agregarlo a
        // cualquier otra ruta de este archivo.
        Route::middleware('role.custom:administrador,ingeniero')->group(function (): void {
            Route::middleware('permission.custom:reportes.ver')->group(function (): void {
                Route::get('reports', [ApiReportController::class, 'index']);
                Route::get('reports/{type}/{format}', [ApiReportController::class, 'export'])
                    ->whereIn('type', ['ingresos', 'citas', 'inventario', 'clientes'])
                    ->whereIn('format', ['json', 'pdf', 'excel']);
            });

            Route::get('logs', [ApiLogController::class, 'index'])
                ->middleware('permission.custom:logs.ver');
        });

        // Notificaciones
        Route::get('notifications', [ApiNotificationController::class, 'index']);
        Route::post('notifications/read-all', [ApiNotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [ApiNotificationController::class, 'markOneRead']);
        Route::delete('notifications/{id}', [ApiNotificationController::class, 'destroy']);
        Route::get('notifications/preferences', [ApiNotificationController::class, 'preferences']);
        Route::patch('notifications/preferences', [ApiNotificationController::class, 'updatePreferences']);

        // Push (Web Push / VAPID)
        Route::get('push/vapid-public-key', [PushController::class, 'vapidPublicKey']);
        Route::post('push/subscribe', [PushController::class, 'subscribe']);
        Route::delete('push/subscribe', [PushController::class, 'unsubscribe']);

        // Social
        Route::post('social/work/{work}/react', [ApiSocialController::class, 'react']);
        Route::post('social/work/{work}/save', [ApiSocialController::class, 'save']);
        Route::post('social/work/{work}/comment', [ApiSocialController::class, 'comment']);

        // Chatbot - Gestión (Historial, Perfil, Estadísticas)
        Route::get('chatbot/history', [ChatbotManagementController::class, 'getHistory']);
        Route::post('chatbot/clear-history', [ChatbotManagementController::class, 'clearHistory']);
        Route::get('chatbot/profile', [ChatbotManagementController::class, 'getProfile']);
        Route::get('chatbot/learning-stats', [ChatbotManagementController::class, 'getLearningStats']);
        Route::post('chatbot/train-history', [ChatbotManagementController::class, 'trainFromHistory'])
            ->middleware('role.custom:administrador');
        Route::post('chatbot/feedback', [ChatbotManagementController::class, 'feedback']);

        // Solo barbero: su propio perfil/bio, portafolio y horario de trabajo.
        Route::middleware('role.custom:barbero')->group(function (): void {
            Route::get('barber/agenda', [AppointmentController::class, 'barberAgenda']);

            // Bio/perfil propio del Barbero
            Route::get('barber/me', [ProfileController::class, 'showBarberProfile']);
            Route::post('barber/profile', [ProfileController::class, 'updateBarberProfile']);
            Route::get('barber/bio', [ProfileController::class, 'showBarberBio']);
            Route::put('barber/bio', [ProfileController::class, 'updateBarberBio']);

            // Portafolio de Barbero
            Route::get('barber/portfolio', [BarberPortfolioController::class, 'index']);
            Route::post('barber/works', [BarberPortfolioController::class, 'store']);
            Route::delete('barber/works/{work}', [BarberPortfolioController::class, 'destroy']);

            // Horarios de Barbero
            Route::get('barber/schedule', [BarberScheduleController::class, 'show']);
            Route::put('barber/schedule', [BarberScheduleController::class, 'update']);
        });

        // Panel de administración móvil: dashboards, reportes, predicciones/
        // insights de IA, y el nuevo estado del servidor -- todo de solo
        // lectura, así que administrador E ingeniero. La gestión real de
        // barberos/clientes/inventario (con POST/PUT/DELETE) vive en el
        // grupo de abajo, solo administrador.
        Route::prefix('admin')->middleware('role.custom:administrador,ingeniero')->group(function (): void {
            Route::get('dashboard/stats', [DashboardAdminController::class, 'getStats']);
            Route::get('dashboard/appointments', [DashboardAdminController::class, 'getUpcomingAppointments']);
            Route::get('dashboard/revenue', [DashboardAdminController::class, 'getRevenue']);
            Route::get('dashboard/alerts', [DashboardAdminController::class, 'getAlerts']);
            Route::get('dashboard/metrics', [DashboardAdminController::class, 'getMetrics']);

            // Reportes (Phase 3)
            Route::middleware('permission.custom:reportes.ver')->group(function (): void {
                Route::get('reports/revenue', [ReportAdminController::class, 'generateRevenueReport']);
                Route::get('reports/appointments', [ReportAdminController::class, 'generateAppointmentsReport']);
                Route::get('reports/inventory', [ReportAdminController::class, 'generateInventoryReport']);
                Route::get('reports/clients', [ReportAdminController::class, 'generateClientsReport']);
                Route::get('reports/barber-commissions', [ReportAdminController::class, 'generateBarberCommissionsReport']);
            });

            // Predicciones e Insights (IA)
            Route::get('predictions/income/{days?}', [PredictionController::class, 'incomeForecasting']);
            Route::get('predictions/appointments/{days?}', [PredictionController::class, 'appointmentForecast']);
            Route::get('predictions/services', [PredictionController::class, 'serviceAnalysis']);
            Route::get('predictions/peak-hours', [PredictionController::class, 'peakHoursAnalysis']);
            Route::get('predictions/insights', [PredictionController::class, 'insights']);

            // Estado del servidor (rol ingeniero): Mongo/Redis, cola, tareas
            // programadas. Nueva en esta fase -- ver App\Services\System.
            Route::get('system/status', [SystemController::class, 'status'])
                ->middleware('permission.custom:sistema.ver');
        });

        // Gestión real (crea/edita/borra) de barberos, clientes e
        // inventario -- solo administrador, nunca ingeniero.
        Route::prefix('admin')->middleware('role.custom:administrador')->group(function (): void {
            // Barberos (Phase 2)
            Route::get('barbers', [BarberAdminController::class, 'getBarbers']);
            Route::get('barbers/{barber}', [BarberAdminController::class, 'show']);
            Route::get('barbers/{barber}/schedule', [BarberAdminController::class, 'getSchedule']);
            Route::get('barbers/{barber}/clients', [BarberAdminController::class, 'getRegularClients']);
            Route::get('barbers/{barber}/performance', [BarberAdminController::class, 'getPerformanceStats']);
            Route::put('barbers/{barber}', [BarberAdminController::class, 'update']);

            // Clientes (Phase 2) — solo administrador.
            // Exportar la base completa de clientes es PII de todo el negocio
            // en un CSV, la segmentación es información comercial y la baja es
            // destructiva: las tres se quedan aquí. El resto de la atención de
            // clientes vive en el grupo de mostrador, más abajo.
            Route::get('clients/segmentation/data', [ClientAdminController::class, 'getSegmentation']);
            Route::get('clients/export', [ClientAdminController::class, 'export']);
            Route::delete('clients/{client}', [ClientAdminController::class, 'destroy']);

            // Inventario (Phase 3)
            Route::get('inventory/products', [InventoryAdminController::class, 'getProducts']);
            Route::get('inventory/products/{productId}', [InventoryAdminController::class, 'show']);
            Route::post('inventory/products', [InventoryAdminController::class, 'store']);
            Route::put('inventory/products/{productId}', [InventoryAdminController::class, 'update']);
            Route::delete('inventory/products/{productId}', [InventoryAdminController::class, 'destroy']);
            Route::post('inventory/products/{productId}/movement', [InventoryAdminController::class, 'recordMovement']);
            Route::get('inventory/movements', [InventoryAdminController::class, 'getMovements']);
            Route::get('inventory/summary', [InventoryAdminController::class, 'getSummary']);
            Route::get('inventory/low-stock', [InventoryAdminController::class, 'getLowStockProducts']);
        });

        // Atención de clientes desde el mostrador: recepción entra igual que
        // administración. Mantienen el prefijo admin/ porque son las mismas
        // rutas que ya consume el frontend -- cambia quién puede llamarlas, no
        // su forma. Cada método revalida el rol con authorizeCounterStaff().
        //
        // Va DESPUÉS del grupo de arriba a propósito: 'clients/{client}' es un
        // comodín y Laravel resuelve por orden de registro, así que declararlo
        // antes haría que /admin/clients/export y /admin/clients/segmentation/
        // data entraran aquí con {client} = "export" y murieran en un 404 de
        // route-model binding.
        Route::prefix('admin')->middleware('role.custom:administrador,recepcionista')->group(function (): void {
            Route::get('clients', [ClientAdminController::class, 'getClients']);
            Route::get('clients/{client}', [ClientAdminController::class, 'show']);
            Route::post('clients', [ClientAdminController::class, 'store']);
            Route::put('clients/{client}', [ClientAdminController::class, 'update']);
        });

        // Reseña de barbero: requiere sesión de cliente autenticado.
        Route::post('barbers/{barber}/review', [CatalogController::class, 'storeReview']);
    });

    // Detalle público de barbero (después de barbers/manage para no chocar con
    // el wildcard). Movido fuera de mobile.auth el 2026-09-09: el perfil
    // público de un barbero (portafolio, reseñas) debe verse sin iniciar
    // sesión, igual que /services y /barbers -- CatalogController::showBarber
    // ya trata $request->user() como opcional (canReview/already_reviewed
    // quedan en false para visitantes anónimos).
    Route::get('barbers/{barber}', [CatalogController::class, 'showBarber']);
});
