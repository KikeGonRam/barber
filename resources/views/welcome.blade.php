<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Barbershop') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            body { font-family: Figtree, sans-serif; margin: 0; color: #0d0d0d; background: linear-gradient(140deg, #f2f2f2, #e9e9e9); }
            .container { max-width: 1140px; margin: 0 auto; padding: 24px; }
        </style>
    @endif
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen">
        <header class="container">
            <nav class="ui-panel flex items-center justify-between px-5 py-4">
                <div class="flex items-center gap-3 text-[#f2f2f2]">
                    <x-application-logo class="h-8 w-8 fill-current" />
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Barbershop Suite</p>
                        <p class="text-xs text-[#cfcfcf]">Gestion premium de barberia</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="ui-btn">Ir al dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="ui-btn-secondary">Iniciar sesion</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="ui-btn">Crear cuenta</a>
                        @endif
                    @endauth
                </div>
            </nav>
        </header>

        <main class="container pb-10">
            <section class="grid grid-cols-1 gap-5 lg:grid-cols-[1.2fr_1fr]">
                <article class="ui-card-premium p-8">
                    <p class="ui-badge mb-3">Plataforma integral para barberia</p>
                    <h1 class="text-3xl font-semibold leading-tight text-[#0d0d0d] sm:text-4xl">
                        Controla citas, caja e inventario en una sola vista.
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-relaxed text-[#5a5a5a]">
                        Disenado para recepcion y administracion: agenda de citas, pagos diarios, servicios, productos y reportes exportables.
                        Flujo rapido para operar sin friccion durante horas pico.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-2">
                        @auth
                            <a href="{{ route('dashboard') }}" class="ui-btn">Abrir panel operativo</a>
                        @else
                            <a href="{{ route('login') }}" class="ui-btn">Entrar al sistema</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="ui-btn-secondary">Registrar negocio</a>
                            @endif
                        @endauth
                    </div>
                </article>

                <article class="ui-card-premium p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[#3f3f3f]">Flujo diario sugerido</h2>
                    <ol class="mt-4 space-y-3 text-sm text-[#4b4b4b]">
                        <li class="rounded-lg border border-[#c8c8c8] bg-[#f7f7f7] p-3">
                            <p class="font-semibold text-[#1f1f1f]">1. Abrir agenda</p>
                            <p class="mt-1 text-xs">Validar disponibilidad y registrar citas del dia.</p>
                        </li>
                        <li class="rounded-lg border border-[#c8c8c8] bg-[#f7f7f7] p-3">
                            <p class="font-semibold text-[#1f1f1f]">2. Cobrar servicios</p>
                            <p class="mt-1 text-xs">Registrar pago, metodo y comprobante de forma inmediata.</p>
                        </li>
                        <li class="rounded-lg border border-[#c8c8c8] bg-[#f7f7f7] p-3">
                            <p class="font-semibold text-[#1f1f1f]">3. Revisar inventario</p>
                            <p class="mt-1 text-xs">Detectar stock bajo y actualizar movimientos de productos.</p>
                        </li>
                        <li class="rounded-lg border border-[#c8c8c8] bg-[#f7f7f7] p-3">
                            <p class="font-semibold text-[#1f1f1f]">4. Cerrar con reportes</p>
                            <p class="mt-1 text-xs">Exportar ingresos, citas e inventario en PDF o Excel.</p>
                        </li>
                    </ol>
                </article>
            </section>

            <section class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[1.4fr_1fr]">
                <article class="ui-card-premium p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold">Vista operativa</h3>
                        <span class="ui-badge">Realtime</span>
                    </div>
                    <div class="mt-4 rounded-xl border border-[#c8c8c8] bg-[#f7f7f7] p-4">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-lg border border-[#d0d0d0] bg-[#efefef] p-3">
                                <p class="text-[11px] uppercase tracking-wide text-[#6a6a6a]">Citas hoy</p>
                                <p class="mt-1 text-xl font-semibold">18</p>
                            </div>
                            <div class="rounded-lg border border-[#d0d0d0] bg-[#efefef] p-3">
                                <p class="text-[11px] uppercase tracking-wide text-[#6a6a6a]">Ingresos</p>
                                <p class="mt-1 text-xl font-semibold">$5,640</p>
                            </div>
                            <div class="rounded-lg border border-[#d0d0d0] bg-[#efefef] p-3">
                                <p class="text-[11px] uppercase tracking-wide text-[#6a6a6a]">Stock bajo</p>
                                <p class="mt-1 text-xl font-semibold">4</p>
                            </div>
                        </div>
                        <div class="ui-divider my-4"></div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between rounded-md border border-[#d4d4d4] bg-[#f2f2f2] px-3 py-2 text-sm">
                                <span>Corte clasico - 10:00</span><span class="ui-badge">Confirmada</span>
                            </div>
                            <div class="flex items-center justify-between rounded-md border border-[#d4d4d4] bg-[#f2f2f2] px-3 py-2 text-sm">
                                <span>Barba premium - 11:30</span><span class="ui-badge">En proceso</span>
                            </div>
                            <div class="flex items-center justify-between rounded-md border border-[#d4d4d4] bg-[#f2f2f2] px-3 py-2 text-sm">
                                <span>Combo ejecutivo - 13:00</span><span class="ui-badge">Pendiente</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="ui-card-premium p-5">
                    <h3 class="text-base font-semibold">Beneficios de negocio</h3>
                    <ul class="mt-4 space-y-3 text-sm text-[#4f4f4f]">
                        <li class="rounded-md border border-[#cfcfcf] bg-[#f7f7f7] px-3 py-2">Menos tiempo en captura manual de citas y cobros.</li>
                        <li class="rounded-md border border-[#cfcfcf] bg-[#f7f7f7] px-3 py-2">Visibilidad diaria para tomar decisiones de caja e inventario.</li>
                        <li class="rounded-md border border-[#cfcfcf] bg-[#f7f7f7] px-3 py-2">Reportes listos para auditoria interna o cierre mensual.</li>
                    </ul>
                </article>
            </section>

            <section class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="ui-card-premium p-5">
                    <div class="mb-3 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#c7c7c7] bg-[#ececec]">
                        <svg class="h-5 w-5 text-[#2f2f2f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                    </div>
                    <h3 class="text-base font-semibold">Agenda de citas</h3>
                    <p class="mt-1 text-sm text-[#666]">Vista diaria y semanal para recepcion y seguimiento.</p>
                </article>

                <article class="ui-card-premium p-5">
                    <div class="mb-3 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#c7c7c7] bg-[#ececec]">
                        <svg class="h-5 w-5 text-[#2f2f2f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M2.5 10h19M7 14h3"/></svg>
                    </div>
                    <h3 class="text-base font-semibold">Caja y pagos</h3>
                    <p class="mt-1 text-sm text-[#666]">Control de ingresos, propinas y comprobantes de pago.</p>
                </article>

                <article class="ui-card-premium p-5">
                    <div class="mb-3 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#c7c7c7] bg-[#ececec]">
                        <svg class="h-5 w-5 text-[#2f2f2f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/></svg>
                    </div>
                    <h3 class="text-base font-semibold">Inventario</h3>
                    <p class="mt-1 text-sm text-[#666]">Productos, movimientos y alertas de stock minimo.</p>
                </article>

                <article class="ui-card-premium p-5">
                    <div class="mb-3 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#c7c7c7] bg-[#ececec]">
                        <svg class="h-5 w-5 text-[#2f2f2f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 20h16M7 16V8M12 16V4M17 16v-6"/></svg>
                    </div>
                    <h3 class="text-base font-semibold">Reportes</h3>
                    <p class="mt-1 text-sm text-[#666]">Exportacion por filtros para decisiones de negocio.</p>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
