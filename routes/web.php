<?php

use App\Http\Controllers\Appointment\AppointmentController;
use App\Http\Controllers\Barber\BarberController;
use App\Http\Controllers\Barber\BarberDashboardController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Client\ClientAppointmentController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DatabaseBackupController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Log\ActivityLogController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Service\ServiceController;
use App\Http\Controllers\Setting\BarbershopSettingController;
use App\Http\Controllers\Social\BarberPortfolioController;
use App\Http\Controllers\Social\SocialController;
use App\Http\Controllers\User\UserController;
use App\Models\Barber;
use App\Models\Service;

Route::get('/', function () {
    $services = Service::where('activo', true)->limit(6)->get();
    $barbers  = Barber::with('user')->where('activo', true)->limit(4)->get();

    $statsGlobales = [
        'clientes'  => \App\Models\Client::count(),
        'servicios' => \App\Models\Service::where('activo', true)->count(),
        'citas'     => \App\Models\Appointment::where('estado', 'completada')->count(),
        'rating'    => number_format((float) (\App\Models\Comment::whereNotNull('rating')->avg('rating') ?? 4.9), 1),
        'resenas'   => \App\Models\Comment::whereNotNull('rating')->count(),
    ];

    return view('welcome', compact('services', 'barbers', 'statsGlobales'));
});

Route::get('/mantenimiento', function () {
    return view('errors.maintenance');
})->name('maintenance');

Route::get('/equipo/{barber}', [BarberController::class, 'show'])->whereNumber('barber')->name('barbers.public.show');
Route::get('/barbero/{barber}', [BarberController::class, 'show'])->whereNumber('barber')->name('barbers.show');
Route::get('/servicios', [ServiceController::class, 'publicIndex'])->name('services.public.index');
Route::post('/chatbot/query', [ChatbotController::class, 'query'])->name('chatbot.query');
Route::middleware(['auth'])->group(function () {
    Route::get('/chatbot/history', [ChatbotController::class, 'getHistory'])->name('chatbot.history');
    Route::get('/chatbot/profile', [ChatbotController::class, 'getProfile'])->name('chatbot.profile');
    Route::post('/chatbot/clear-history', [ChatbotController::class, 'clearHistory'])->name('chatbot.clear-history');
    Route::get('/chatbot/learning-stats', [ChatbotController::class, 'getLearningStats'])->name('chatbot.learning-stats');
    Route::post('/chatbot/train-history', [ChatbotController::class, 'trainFromHistory'])->name('chatbot.train-history');
});

// Social Feed (Instagram Style)
Route::get('/descubrir', [SocialController::class, 'feed'])->name('social.feed');

Route::middleware(['auth', 'verified'])->group(function () {
    // Interactions
    Route::post('/social/work/{work}/react', [SocialController::class, 'react'])->name('social.react');
    Route::post('/social/work/{work}/save', [SocialController::class, 'save'])->name('social.save');
    Route::post('/social/work/{work}/comment', [SocialController::class, 'comment'])->name('social.comment');

    Route::middleware(['role.custom:administrador'])->group(function () {
        Route::post('/settings/maintenance', [BarbershopSettingController::class, 'toggleMaintenance'])->name('settings.maintenance.toggle');
    });
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Web-session based API token retrieval for the dashboard
Route::post('/api/v1/auth/get-api-token', [App\Http\Controllers\Api\AuthController::class, 'getWebApiToken'])
    ->middleware(['web', 'auth'])
    ->name('api.get-token');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::middleware(['verified', 'role.custom:administrador,recepcionista'])->group(function () {
        Route::middleware('permission.custom:citas.gestionar')->group(function () {
            Route::resource('appointments', AppointmentController::class)->except('show');
            Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.update-status');
            Route::get('appointments-calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
            Route::get('appointments-calendar/data', [AppointmentController::class, 'calendarData'])->name('appointments.calendar.data');
        });

        Route::middleware('permission.custom:pagos.gestionar')->group(function () {
            Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'destroy']);
            Route::get('payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt'])->name('payments.receipt.download');
        });

        Route::middleware('permission.custom:inventario.ver,inventario.gestionar')->group(function () {
            Route::resource('inventory/movements', InventoryMovementController::class)
                ->only(['index', 'create', 'store'])
                ->names('inventory.movements');
        });

        Route::middleware('permission.custom:clientes.gestionar')->group(function () {
            Route::resource('clients', ClientController::class)
                ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
            Route::get('clients/{client}/profile', [\App\Http\Controllers\Client\ClientController::class, 'show'])->name('clients.show');
        });

    });

    Route::middleware(['verified', 'role.custom:administrador'])->group(function () {
        Route::get('backups/database', [DatabaseBackupController::class, 'download'])->name('backups.database.download');

        Route::middleware('permission.custom:reportes.ver')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{type}/{format}', [ReportController::class, 'export'])
                ->whereIn('type', ['ingresos', 'citas', 'inventario', 'clientes'])
                ->whereIn('format', ['pdf', 'excel'])
                ->name('reports.export');
        });

        Route::middleware('permission.custom:servicios.gestionar')->group(function () {
            Route::resource('services', ServiceController::class)->except('show');
        });

        Route::middleware('permission.custom:inventario.gestionar')->group(function () {
            Route::resource('inventory/products', ProductController::class)
                ->except('show')
                ->names('inventory.products');
        });

        Route::middleware('permission.custom:usuarios.gestionar')->group(function () {
            Route::resource('users', UserController::class)->except('show');
        });

        Route::middleware('permission.custom:barberos.gestionar')->group(function () {
            Route::resource('barbers', BarberController::class)
                ->only(['index', 'edit', 'update']);
            Route::get('barbers/{barber}/performance', [\App\Http\Controllers\Barber\BarberController::class, 'performance'])->name('barbers.performance');
        });

        Route::middleware('permission.custom:configuracion.gestionar')->group(function () {
            Route::get('settings', [BarbershopSettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [BarbershopSettingController::class, 'update'])->name('settings.update');
        });

        Route::middleware('permission.custom:logs.ver')->group(function () {
            Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index');
        });
    });

    Route::middleware(['verified', 'role.custom:cliente'])->prefix('cliente')->name('client.')->group(function () {
        Route::resource('appointments', ClientAppointmentController::class)->except('show');
    });

    Route::middleware(['verified', 'role.custom:barbero'])->prefix('barbero')->name('barber.')->group(function () {
        Route::get('agenda', [BarberDashboardController::class, 'agenda'])->name('agenda');
        Route::patch('appointments/{appointment}/status', [BarberDashboardController::class, 'updateAppointmentStatus'])->name('appointments.status');
        Route::get('profile', [BarberDashboardController::class, 'editProfile'])->name('profile.edit');
        Route::put('profile', [BarberDashboardController::class, 'updateProfile'])->name('profile.update');

        // Schedule Management
        Route::get('schedule', [BarberDashboardController::class, 'editSchedule'])->name('schedule.edit');
        Route::put('schedule', [BarberDashboardController::class, 'updateSchedule'])->name('schedule.update');

        // Portfolio Management
        Route::get('portfolio', [BarberPortfolioController::class, 'index'])->name('portfolio.index');
        Route::get('portfolio/create', [BarberPortfolioController::class, 'create'])->name('portfolio.create');
        Route::post('portfolio', [BarberPortfolioController::class, 'store'])->name('portfolio.store');
        Route::delete('portfolio/{work}', [BarberPortfolioController::class, 'destroy'])->name('portfolio.destroy');
    });

    Route::middleware(['verified', 'role.custom:barbero'])->group(function () {
        Route::post('/barbero/{barber}/works', [BarberPortfolioController::class, 'store'])->name('barbers.works.store');
    });

    Route::middleware('auth')->group(function () {
        Route::resource('almacen', InventoryController::class)
            ->parameters(['almacen' => 'inventory'])
            ->names('almacen');
    });
});

require __DIR__.'/auth.php';
