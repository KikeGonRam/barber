@php
    use Illuminate\Support\Str;

    /**
     * Detectar el tipo REAL del servicio por su nombre,
     * ignorando la categoría de la BD (datos de demo mal asignados).
     */
    function detectServiceType(string $nombre): string {
        $n = Str::lower($nombre);
        if (Str::contains($n, ['fade','degradado','clasico','clásico','corte','haircut','pelo','cabello','pompadour','undercut','tapado','mohicano'])) {
            return 'corte';
        }
        if (Str::contains($n, ['barba','beard','afeitado','shave','bigote'])) {
            return 'barba';
        }
        if (Str::contains($n, ['combo','pack','full','completo'])) {
            return 'combo';
        }
        if (Str::contains($n, ['tratamiento','capilar','hidrat','keratina','color','tinte','nutrici'])) {
            return 'tratamiento';
        }
        return 'general';
    }

    /**
     * Pool de foto IDs de Unsplash verificados por tipo.
     * Todos son fotos reales de barbería bien documentadas.
     */
    $imgPools = [
        'corte' => [
            // Hombre recibiendo corte / fade en silla de barbería
            'photo-1503951914875-452162b0f3f1?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1599351431202-1e0f0137899a?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1622286342621-4bd786c2447c?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1534297635766-a262cdcb8ee4?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1605497787928-40e1c74e4e74?q=85&w=800&h=600&auto=format&fit=crop',
        ],
        'barba' => [
            // Arreglo / afeitado de barba
            'photo-1621605815971-fbc98d665033?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1583863788434-e58a36330cf0?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1617450365226-9bf28c04e130?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1580618672591-eb180b1a973f?q=85&w=800&h=600&auto=format&fit=crop',
        ],
        'combo' => [
            // Servicio completo / barbería premium
            'photo-1585747860715-2ba37e788b70?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1517832606539-4a5bae9f7090?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1503951914875-452162b0f3f1?q=85&w=800&h=600&auto=format&fit=crop',
        ],
        'tratamiento' => [
            // Tratamientos capilares / spa capilar
            'photo-1519823551278-64ac92734fb1?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1570172619644-dfd03ed5d881?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1552642762-f55d06580641?q=85&w=800&h=600&auto=format&fit=crop',
        ],
        'general' => [
            'photo-1585747860715-2ba37e788b70?q=85&w=800&h=600&auto=format&fit=crop',
            'photo-1503951914875-452162b0f3f1?q=85&w=800&h=600&auto=format&fit=crop',
        ],
    ];

    // Labels para los filtros por tipo REAL (no la categoría BD)
    $typeLabels = [
        'corte'      => 'Cortes',
        'barba'      => 'Barba & Afeitado',
        'combo'      => 'Combos',
        'tratamiento'=> 'Tratamientos',
        'general'    => 'Otros',
    ];

    // Colores por tipo
    $typePalette = [
        'corte'       => ['pill' => 'bg-sky-500/20 text-sky-300 border-sky-500/30',     'dot' => 'bg-sky-400'],
        'barba'       => ['pill' => 'bg-amber-500/20 text-amber-300 border-amber-500/30','dot' => 'bg-amber-400'],
        'combo'       => ['pill' => 'bg-purple-500/20 text-purple-300 border-purple-500/30','dot'=>'bg-purple-400'],
        'tratamiento' => ['pill' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30','dot'=>'bg-emerald-400'],
        'general'     => ['pill' => 'bg-white/10 text-white/60 border-white/15',          'dot' => 'bg-white/40'],
    ];

    // Agrupar servicios por tipo detectado
    $servicesWithType = $services->map(function ($s) {
        $s->realType = detectServiceType($s->nombre);
        return $s;
    });
    $grouped = $servicesWithType->groupBy('realType');
    $allTypes = $grouped->keys()->toArray();

    // Rutas de reserva según auth
    $isClient = auth()->check() && auth()->user()->hasRole('cliente');
    $isStaff  = auth()->check() && !$isClient;
    $cta = [
        'href'  => $isClient ? route('client.appointments.create') : ($isStaff ? route('dashboard') : route('register')),
        'label' => $isClient ? 'Reservar Cita' : ($isStaff ? 'Ir al Panel' : 'Registrarme'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servicios Elite — BarberPro</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet"/>
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* ── Grain ────────────────────────────────────── */
        body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.02;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size:200px;}

        /* ── Reveal ───────────────────────────────────── */
        .reveal{opacity:0;transform:translateY(22px);transition:opacity .55s ease,transform .55s ease;}
        .reveal.in{opacity:1;transform:none;}

        /* ── Card ─────────────────────────────────────── */
        .svc-card{
            position:relative;
            background:linear-gradient(160deg,#161616 0%,#0f0f0f 100%);
            border:1px solid rgba(255,255,255,.07);
            border-radius:1.5rem;
            overflow:hidden;
            transition:border-color .35s ease, box-shadow .35s ease, transform .35s ease;
            box-shadow:0 12px 40px rgba(0,0,0,.55);
        }
        .svc-card:hover{
            border-color:rgba(212,175,55,.35);
            box-shadow:0 24px 70px rgba(0,0,0,.7),0 0 40px rgba(212,175,55,.08);
            transform:translateY(-6px);
        }

        /* ── Image wrapper ────────────────────────────── */
        .svc-img-wrap{position:relative;overflow:hidden;}
        .svc-img-wrap img{
            width:100%;height:100%;object-fit:cover;
            transition:transform .65s ease, filter .5s ease;
            filter:brightness(.85) saturate(.9);
        }
        .svc-card:hover .svc-img-wrap img{
            transform:scale(1.08);
            filter:brightness(.95) saturate(1.1);
        }

        /* Shimmer while loading */
        .img-load{background:linear-gradient(90deg,#181818 25%,#242424 50%,#181818 75%);
            background-size:200% 100%;animation:shimmer 1.6s infinite;}
        @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

        /* ── Featured (primer card de cada sección) ───── */
        .svc-card.featured{display:grid;grid-template-columns:55% 45%;}
        .svc-card.featured .svc-img-wrap{height:100%;min-height:280px;}
        @media(max-width:767px){.svc-card.featured{display:flex;flex-direction:column;}
            .svc-card.featured .svc-img-wrap{height:220px;}}

        /* ── Price tag ────────────────────────────────── */
        .price-tag{
            position:absolute;top:1rem;right:1rem;
            background:rgba(0,0,0,.75);
            backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,.1);
            border-radius:9999px;
            padding:.35rem .9rem;
            font-weight:900;color:#fff;
            font-size:.85rem;
            letter-spacing:-.01em;
        }

        /* ── Category pill ────────────────────────────── */
        .cat-pill{
            backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
            border-radius:9999px;border:1px solid;
            padding:.25rem .75rem;
            font-size:.6rem;font-weight:900;text-transform:uppercase;letter-spacing:.15em;
        }

        /* ── Popular badge ────────────────────────────── */
        .popular-badge{
            position:absolute;top:1rem;left:1rem;
            background:linear-gradient(135deg,#d4af37,#aa8c2c);
            color:#000;border-radius:9999px;
            padding:.2rem .7rem;
            font-size:.6rem;font-weight:900;text-transform:uppercase;letter-spacing:.15em;
            box-shadow:0 4px 12px rgba(212,175,55,.4);
        }

        /* ── CTA button hover fill ────────────────────── */
        .cta-btn{
            display:flex;align-items:center;justify-content:space-between;
            width:100%;padding:.85rem 1.25rem;border-radius:.875rem;
            border:1px solid rgba(255,255,255,.1);
            background:rgba(255,255,255,.04);
            color:#fff;
            font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.18em;
            transition:all .3s ease;
        }
        .cta-btn:hover{background:var(--gold);border-color:var(--gold);color:#000;}
        .cta-btn svg{transition:transform .3s ease;}
        .cta-btn:hover svg{transform:translateX(4px);}

        /* ── Filter pill active ───────────────────────── */
        .filter-pill{
            border-radius:9999px;border:1px solid rgba(255,255,255,.1);
            padding:.45rem 1.2rem;
            font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.18em;
            transition:all .3s ease;cursor:pointer;
            background:transparent;color:rgba(255,255,255,.5);
        }
        .filter-pill:hover{color:#fff;border-color:rgba(212,175,55,.3);}
        .filter-pill.active{background:var(--gold);border-color:var(--gold);color:#000;}

        /* ── Section divider ──────────────────────────── */
        .section-divider{
            display:flex;align-items:center;gap:1.25rem;
            margin-bottom:2.5rem;
        }
        .section-divider-line{flex:1;height:1px;background:linear-gradient(to right,transparent,rgba(255,255,255,.07));}
        .section-divider-line.right{background:linear-gradient(to left,transparent,rgba(255,255,255,.07));}
    </style>
</head>

<body class="font-sans antialiased text-white bg-[#050505]">

<!-- ══════════════════════════════════════════════ -->
<!-- NAVBAR                                         -->
<!-- ══════════════════════════════════════════════ -->
<nav class="sticky top-0 z-50 bg-[#050505]/95 backdrop-blur-xl border-b border-white/[0.06]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between">
            <a href="/" class="flex items-center gap-3 group">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-gold to-gold-dim shadow-lg shadow-gold/25 group-hover:shadow-gold/40 transition-all">
                    <svg class="h-6 w-6 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span class="text-xl font-black uppercase tracking-tighter">Barber<span class="text-gold">Pro</span></span>
            </a>

            <div class="flex items-center gap-5">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition flex items-center gap-2">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l9-8 9 8v8a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z"/></svg>
                        Mi Panel
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold transition hidden sm:block">
                        Iniciar Sesión
                    </a>
                    <a href="{{ route('register') }}" class="ui-btn py-2.5 px-6 text-[10px] tracking-widest">
                        Reservar
                    </a>
                @endauth
                <a href="/" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold transition flex items-center gap-1.5">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Inicio
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ══════════════════════════════════════════════ -->
<!-- HERO HEADER                                    -->
<!-- ══════════════════════════════════════════════ -->
<header class="relative overflow-hidden" style="padding:7rem 0 5rem;">
    <!-- BG sutil -->
    <div class="absolute inset-0">
        <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1585747860715-2ba37e788b70?q=80&w=2074&auto=format&fit=crop')] bg-cover bg-center opacity-[0.07]"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-[#050505] via-[#050505]/70 to-[#050505]"></div>
    </div>
    <div class="absolute bottom-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold/20 to-transparent"></div>

    <div class="relative z-10 mx-auto max-w-5xl px-4 text-center">
        <p class="text-[10px] font-black uppercase tracking-[0.45em] text-gold mb-5 flex items-center justify-center gap-3">
            <span class="h-px w-8 bg-gold inline-block"></span>
            Carta de Experiencias
            <span class="h-px w-8 bg-gold inline-block"></span>
        </p>
        <h1 class="font-black uppercase tracking-tighter leading-[.88] text-white mb-6"
            style="font-size:clamp(3.5rem,9vw,7rem);">
            Servicios<br><span class="text-gradient-gold italic font-serif lowercase normal-case" style="font-size:clamp(3rem,8vw,6.5rem);">de élite</span>
        </h1>
        <p class="text-muted max-w-lg mx-auto text-base leading-relaxed mb-10">
            Cada servicio es un ritual de maestría diseñado para el caballero contemporáneo. Precisión artesanal en cada sesión.
        </p>

        <!-- Filtros -->
        <div class="flex flex-wrap items-center justify-center gap-2.5" id="filters">
            <button class="filter-pill active" data-filter="all">Todos</button>
            @foreach($allTypes as $t)
                @if(isset($typeLabels[$t]))
                    <button class="filter-pill" data-filter="{{ $t }}">{{ $typeLabels[$t] }}</button>
                @endif
            @endforeach
        </div>
    </div>
</header>

<!-- ══════════════════════════════════════════════ -->
<!-- SERVICIOS                                      -->
<!-- ══════════════════════════════════════════════ -->
<main class="pb-32 pt-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" id="services-container">

        @foreach($grouped as $tipo => $tipoServices)
            @php $palette = $typePalette[$tipo] ?? $typePalette['general']; @endphp

            <!-- Separador de sección -->
            <div class="section-divider mb-10 mt-16 first:mt-0 cat-section" data-type="{{ $tipo }}">
                <div class="section-divider-line"></div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <span class="h-2 w-2 rounded-full {{ $palette['dot'] }}"></span>
                    <span class="text-[10px] font-black uppercase tracking-[0.35em] text-white">
                        {{ $typeLabels[$tipo] ?? $tipo }}
                    </span>
                    <span class="text-[9px] font-bold text-muted/50 border border-white/10 rounded-full px-2.5 py-0.5">
                        {{ $tipoServices->count() }}
                    </span>
                </div>
                <div class="section-divider-line right"></div>
            </div>

            <!-- Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 cat-section" data-type="{{ $tipo }}">

                @foreach($tipoServices as $idx => $service)
                    @php
                        $pool   = $imgPools[$tipo] ?? $imgPools['general'];
                        $imgUrl = "https://images.unsplash.com/{$pool[$service->id % count($pool)]}";
                        $isFeatured = ($idx === 0); // Primer card de cada sección = destacada
                        $isPopular  = ($idx < 2);   // Primeras 2 con badge popular
                    @endphp

                    <article
                        class="svc-card reveal {{ $isFeatured && $tipoServices->count() >= 2 ? 'md:col-span-2 lg:col-span-2 featured' : '' }}"
                        data-type="{{ $tipo }}"
                        style="transition-delay: {{ ($idx % 3) * 80 }}ms;">

                        <!-- IMAGEN -->
                        <div class="svc-img-wrap img-load {{ $isFeatured ? '' : 'h-56' }}">
                            <img
                                src="{{ $service->imagen ? \Illuminate\Support\Facades\Storage::url($service->imagen) : $imgUrl }}"
                                alt="{{ $service->nombre }}"
                                loading="lazy"
                                onload="this.closest('.img-load')?.classList.remove('img-load')"
                                onerror="this.src='{{ $imgUrl }}'">

                            <!-- Gradient overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0f0f0f] via-[#0f0f0f]/10 to-transparent"></div>

                            <!-- Popular badge -->
                            @if($isPopular)
                                <div class="popular-badge">★ Popular</div>
                            @endif

                            <!-- Price tag -->
                            <div class="price-tag">${{ number_format($service->precio, 2) }}</div>
                        </div>

                        <!-- CONTENIDO -->
                        <div class="flex flex-col p-7 {{ $isFeatured ? 'justify-center' : '' }}">

                            <!-- Tipo pill -->
                            <span class="cat-pill {{ $palette['pill'] }} w-fit mb-4">
                                {{ $typeLabels[$tipo] ?? $tipo }}
                            </span>

                            <!-- Nombre -->
                            <h3 class="text-2xl font-black text-white uppercase leading-tight group-hover:text-gold transition-colors mb-3">
                                {{ $service->nombre }}
                            </h3>

                            <!-- Descripción -->
                            <p class="text-sm text-muted leading-relaxed {{ $isFeatured ? 'max-w-sm' : '' }} mb-6 flex-1">
                                {{ $service->descripcion ?: 'Servicio de alta precisión ejecutado por nuestros maestros con técnicas artesanales de la más alta calidad.' }}
                            </p>

                            <!-- Detalles -->
                            <div class="flex items-center gap-5 mb-6">
                                <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-muted">
                                    <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/15 flex items-center justify-center">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                                        </svg>
                                    </div>
                                    {{ $service->duracion_min }} min
                                </div>
                                <div class="h-3 w-px bg-white/10"></div>
                                <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-muted">
                                    <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/15 flex items-center justify-center">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-width="2"/>
                                        </svg>
                                    </div>
                                    Garantizado
                                </div>
                            </div>

                            <!-- Precio visible en contenido -->
                            <div class="flex items-baseline gap-2 mb-6">
                                <span class="text-3xl font-black text-white leading-none">${{ number_format($service->precio, 0) }}</span>
                                <span class="text-sm text-muted font-bold">MXN</span>
                                <div class="ml-auto text-right">
                                    <div class="flex gap-0.5">
                                        @for($s=0;$s<5;$s++)<svg class="h-3 w-3 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                                    </div>
                                    <p class="text-[9px] text-muted/60 font-bold mt-0.5">5.0</p>
                                </div>
                            </div>

                            <!-- CTA Botón -->
                            <a href="{{ $cta['href'] }}" class="cta-btn">
                                <span>{{ $cta['label'] }}</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </a>

                            @guest
                                <p class="mt-2.5 text-center text-[9px] font-bold uppercase tracking-wider text-muted/40">
                                    ¿Ya tienes cuenta?
                                    <a href="{{ route('login') }}" class="text-gold/70 hover:text-gold ml-1 transition">Inicia sesión</a>
                                </p>
                            @endguest
                        </div>
                    </article>
                @endforeach

            </div>
        @endforeach

    </div>
</main>

<!-- ══════════════════════════════════════════════ -->
<!-- CTA FINAL                                      -->
<!-- ══════════════════════════════════════════════ -->
<section class="py-28 relative overflow-hidden border-t border-white/[0.05]">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_70%_60%_at_50%_50%,rgba(212,175,55,0.07),transparent)]"></div>
    <div class="absolute inset-0 opacity-[0.03]" style="background-image:linear-gradient(rgba(212,175,55,.5) 1px,transparent 1px),linear-gradient(90deg,rgba(212,175,55,.5) 1px,transparent 1px);background-size:60px 60px;"></div>

    <div class="relative z-10 mx-auto max-w-2xl px-4 text-center reveal">
        <p class="text-[10px] font-black uppercase tracking-[.4em] text-gold mb-5 flex items-center justify-center gap-3">
            <span class="h-px w-6 bg-gold"></span>
            Reserva Ahora
            <span class="h-px w-6 bg-gold"></span>
        </p>
        <h2 class="font-black text-white uppercase tracking-tighter leading-none mb-5"
            style="font-size:clamp(2.5rem,7vw,4.5rem);">
            ¿Listo para tu<br>
            <span class="text-gradient-gold italic font-serif lowercase normal-case"
                  style="font-size:clamp(3rem,8vw,5.5rem);">mejor versión?</span>
        </h2>
        <p class="text-muted mb-10 leading-relaxed text-sm">
            Reserva en segundos y llega con tu maestro esperándote. Sin esperas, sin sorpresas.
        </p>
        <a href="{{ $cta['href'] }}"
           class="ui-btn px-14 py-5 text-[12px] uppercase tracking-[.25em] shadow-[0_0_60px_rgba(212,175,55,.18)] animate-float">
            {{ $cta['label'] }}
            <svg class="h-4 w-4 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer class="border-t border-white/[0.05] py-10 text-center bg-[#030303]">
    <p class="text-[9px] font-black uppercase tracking-[.3em] text-white/15">
        &copy; {{ date('Y') }} BarberPro Elite Grooming &bull; Todos los derechos reservados
    </p>
</footer>

<script>
    // ── Reveal on scroll ──────────────────────────────────
    const revealObs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revealObs.unobserve(e.target); } });
    }, { threshold: .08, rootMargin: '0px 0px -30px 0px' });
    document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

    // ── Filter ────────────────────────────────────────────
    document.querySelectorAll('.filter-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const f = btn.dataset.filter;

            document.querySelectorAll('.cat-section').forEach(s => {
                s.style.display = (f === 'all' || s.dataset.type === f) ? '' : 'none';
            });
            document.querySelectorAll('article[data-type]').forEach(c => {
                const show = f === 'all' || c.dataset.type === f;
                c.style.display = show ? '' : 'none';
                if (show) { c.classList.remove('in'); revealObs.observe(c); }
            });
        });
    });
</script>
</body>
</html>
