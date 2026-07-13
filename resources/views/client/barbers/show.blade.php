<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('client.barberos.index') }}"
               class="h-9 w-9 rounded-xl border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:border-gold/30 hover:text-gold transition-all">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h2 class="ui-title">{{ $barber->user?->name }}</h2>
                <p class="ui-subtitle">Perfil del maestro barbero</p>
            </div>
        </div>
    </x-slot>

    @php
        $foto = $barber->foto
            ? (str_starts_with($barber->foto, 'http') ? $barber->foto : Storage::url($barber->foto))
            : null;
        $initials = mb_strtoupper(mb_substr($barber->user?->name ?? '?', 0, 2));
        $especialidades = array_filter(array_map('trim', explode(',', $barber->especialidades ?? $barber->especialidad ?? '')));
        $primerNombre = explode(' ', $barber->user?->name ?? 'este barbero')[0];
    @endphp

    <div class="py-6 space-y-5">

        {{-- ═══════════════════════════════════════════
             HERO — Banner + Perfil
        ════════════════════════════════════════════ --}}
        <div class="rounded-3xl border border-white/10 overflow-hidden" style="background:#0d0d0d;">

            {{-- Banner foto --}}
            <div class="relative overflow-hidden" style="height:220px; background:#000;">
                @if($foto)
                    <img src="{{ $foto }}" alt=""
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top center;display:block;">
                @else
                    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(212,175,55,0.08) 0%,rgba(0,0,0,0) 60%);"></div>
                    <div style="position:absolute;bottom:-10px;right:-10px;font-size:10rem;font-weight:900;color:rgba(212,175,55,0.04);line-height:1;user-select:none;">
                        {{ $initials }}
                    </div>
                @endif
                {{-- Overlay gradiente sobre el banner --}}
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(13,13,13,0.7) 70%, rgba(13,13,13,1) 100%);"></div>

                {{-- Badge disponibilidad (esquina superior derecha) --}}
                <div class="absolute top-4 right-4 flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-widest backdrop-blur-sm border
                    {{ $disponibleHoy ? 'border-green-500/30 bg-green-950/50 text-green-400' : 'border-white/10 bg-black/50 text-white/40' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $disponibleHoy ? 'bg-green-400 animate-pulse' : 'bg-white/20' }}"></span>
                    {{ $disponibleHoy ? 'Disponible hoy' : 'No disponible hoy' }}
                </div>
            </div>

            {{-- Perfil info --}}
            <div class="px-6 sm:px-8 pb-7" style="margin-top:-52px; position:relative;">
                <div class="flex flex-col sm:flex-row sm:items-end gap-5">

                    {{-- Avatar --}}
                    <div class="flex-shrink-0">
                        @if($foto)
                            <div style="width:100px;height:100px;border-radius:18px;overflow:hidden;border:3px solid rgba(212,175,55,0.5);background:#111;box-shadow:0 8px 32px rgba(0,0,0,0.6);">
                                <img src="{{ $foto }}" alt="{{ $barber->user?->name }}"
                                     style="width:100%;height:100%;object-fit:cover;object-position:top center;display:block;">
                            </div>
                        @else
                            <div style="width:100px;height:100px;border-radius:18px;border:2px solid rgba(212,175,55,0.25);background:linear-gradient(135deg,rgba(212,175,55,0.12) 0%,rgba(212,175,55,0.03) 100%);display:flex;align-items:center;justify-content:center;font-size:2.2rem;font-weight:900;color:#d4af37;box-shadow:0 8px 32px rgba(0,0,0,0.5);">
                                {{ $initials }}
                            </div>
                        @endif
                    </div>

                    {{-- Nombre + Stats --}}
                    <div class="flex-1 min-w-0 pb-1">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h1 style="font-size:1.65rem;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:-0.03em;line-height:1.1;">
                                    {{ $barber->user?->name }}
                                </h1>
                                <p style="font-size:0.6rem;font-weight:700;color:rgba(212,175,55,0.55);text-transform:uppercase;letter-spacing:0.28em;margin-top:3px;">
                                    Master Groomer · UrbanBlade
                                </p>
                            </div>
                            {{-- Stats compactos --}}
                            <div class="flex items-center gap-4 sm:gap-6">
                                <div class="text-center">
                                    <p style="font-size:1.5rem;font-weight:900;color:#d4af37;line-height:1;">{{ $citasCompletadas }}</p>
                                    <p style="font-size:0.55rem;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.15em;">Citas</p>
                                </div>
                                @if($avgRating)
                                <div class="text-center">
                                    <p style="font-size:1.5rem;font-weight:900;color:#d4af37;line-height:1;">{{ $avgRating }}</p>
                                    <p style="font-size:0.55rem;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.15em;">Rating</p>
                                </div>
                                @endif
                                <div class="text-center">
                                    <p style="font-size:1.5rem;font-weight:900;color:#d4af37;line-height:1;">{{ $yearsExp }}+</p>
                                    <p style="font-size:0.55rem;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.15em;">Años</p>
                                </div>
                                @if($totalReviews > 0)
                                <div class="text-center">
                                    <p style="font-size:1.5rem;font-weight:900;color:#d4af37;line-height:1;">{{ $totalReviews }}</p>
                                    <p style="font-size:0.55rem;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.15em;">Reseñas</p>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Especialidades --}}
                        @if(!empty($especialidades))
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($especialidades as $esp)
                                <span style="padding:3px 10px;border-radius:99px;border:1px solid rgba(212,175,55,0.22);background:rgba(212,175,55,0.06);font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.15em;color:rgba(212,175,55,0.8);">
                                    {{ $esp }}
                                </span>
                            @endforeach
                        </div>
                        @endif

                        {{-- Descripción --}}
                        @if($barber->descripcion)
                        <p style="margin-top:12px;font-size:0.82rem;color:rgba(255,255,255,0.5);line-height:1.6;max-width:560px;">
                            {{ $barber->descripcion }}
                        </p>
                        @endif
                    </div>
                </div>

                {{-- CTA --}}
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('client.appointments.create', ['barber_id' => $barber->id]) }}"
                       class="inline-flex items-center gap-2 ui-btn gold-glow"
                       style="padding:10px 24px;font-size:0.68rem;letter-spacing:0.18em;text-transform:uppercase;">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Agendar con {{ $primerNombre }}
                    </a>
                    @if($totalReviews > 0)
                    <a href="#resenas"
                       style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:12px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.6);text-decoration:none;transition:all 0.2s;"
                       onmouseover="this.style.borderColor='rgba(212,175,55,0.3)';this.style.color='rgba(212,175,55,0.9)';"
                       onmouseout="this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='rgba(255,255,255,0.6)';">
                        <svg style="width:14px;height:14px;fill:currentColor;" viewBox="0 0 24 24">
                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                        </svg>
                        Ver {{ $totalReviews }} reseña{{ $totalReviews !== 1 ? 's' : '' }}
                    </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════
             PORTFOLIO
        ════════════════════════════════════════════ --}}
        @if($works->isNotEmpty())
        <section>
            <div class="flex items-center gap-3 mb-4">
                <div style="height:1px;flex:1;background:rgba(255,255,255,0.05);"></div>
                <span style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.3em;color:rgba(212,175,55,0.7);">Portfolio</span>
                <div style="height:1px;flex:1;background:rgba(255,255,255,0.05);"></div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($works as $work)
                    @php
                        $img = $work->images->first();
                        $imgUrl = $img ? (str_starts_with($img->image, 'http') ? $img->image : Storage::url($img->image)) : null;
                    @endphp
                    <div class="group relative rounded-2xl overflow-hidden border border-white/5 aspect-square" style="background:#111;">
                        @if($imgUrl)
                            <img src="{{ $imgUrl }}" alt="{{ $work->title }}"
                                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.5s;">
                        @else
                            <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(212,175,55,0.04) 0%,transparent 100%);display:flex;align-items:center;justify-content:center;">
                                <svg style="width:2rem;height:2rem;color:rgba(212,175,55,0.1);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex flex-col justify-end p-3">
                            <p style="font-size:0.6rem;font-weight:800;color:#fff;text-transform:uppercase;line-height:1.2;" class="line-clamp-1">{{ $work->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                @if($work->reactions->count() > 0)
                                <span style="font-size:0.6rem;color:rgba(255,255,255,0.5);">{{ $work->reactions->count() }}</span>
                                @endif
                                @if($work->comments->count() > 0)
                                <span style="font-size:0.6rem;color:rgba(255,255,255,0.5);">
                                    <svg style="display:inline;width:0.65rem;height:0.65rem;vertical-align:middle;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    {{ $work->comments->count() }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════════════════════════════════
             RESEÑAS
        ════════════════════════════════════════════ --}}
        <section id="resenas">
            <div class="flex items-center gap-3 mb-4">
                <div style="height:1px;flex:1;background:rgba(255,255,255,0.05);"></div>
                <span style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.3em;color:rgba(212,175,55,0.7);">
                    Reseñas @if($totalReviews > 0)({{ $totalReviews }})@endif
                </span>
                <div style="height:1px;flex:1;background:rgba(255,255,255,0.05);"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {{-- Columna izquierda: resumen + formulario --}}
                <div class="space-y-4">

                    {{-- Resumen de ratings --}}
                    @if($totalReviews > 0)
                    <div class="rounded-2xl border border-white/8 p-5" style="background:#0d0d0d;">
                        <div class="flex items-center gap-4 mb-4">
                            <div>
                                <p style="font-size:3rem;font-weight:900;color:#d4af37;line-height:1;">{{ $avgRating }}</p>
                                <div class="flex gap-0.5 mt-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg style="width:14px;height:14px;fill:{{ $i <= round($avgRating) ? '#d4af37' : 'rgba(255,255,255,0.12)' }};" viewBox="0 0 24 24">
                                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <p style="font-size:0.65rem;color:rgba(255,255,255,0.35);margin-top:4px;">{{ $totalReviews }} reseña{{ $totalReviews !== 1 ? 's' : '' }}</p>
                            </div>
                        </div>
                        {{-- Barras por estrellas --}}
                        @for($star = 5; $star >= 1; $star--)
                            @php $count = $ratingCounts->get($star, 0); $pct = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0; @endphp
                            <div class="flex items-center gap-2 mb-1.5">
                                <span style="font-size:0.6rem;font-weight:700;color:rgba(255,255,255,0.4);width:8px;text-align:right;">{{ $star }}</span>
                                <svg style="width:10px;height:10px;fill:#d4af37;flex-shrink:0;" viewBox="0 0 24 24">
                                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                </svg>
                                <div style="flex:1;height:5px;border-radius:99px;background:rgba(255,255,255,0.06);overflow:hidden;">
                                    <div style="height:100%;border-radius:99px;background:#d4af37;width:{{ round($pct) }}%;transition:width 0.6s;"></div>
                                </div>
                                <span style="font-size:0.6rem;color:rgba(255,255,255,0.3);width:14px;">{{ $count }}</span>
                            </div>
                        @endfor
                    </div>
                    @endif

                    {{-- Formulario de reseña --}}
                    @if($canReview)
                    <div class="rounded-2xl border border-gold/15 p-5" style="background:#0d0d0d;"
                         x-data="{ rating: 0, hover: 0 }">
                        <p style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.2em;color:rgba(212,175,55,0.8);margin-bottom:12px;">
                            Tu reseña
                        </p>

                        @error('rating')
                            <p class="mb-3 text-xs text-red-400">{{ $message }}</p>
                        @enderror

                        <form action="{{ route('client.barberos.review', $barber) }}" method="POST">
                            @csrf

                            {{-- Selector de estrellas --}}
                            <div class="flex gap-1.5 mb-4">
                                @for($i = 1; $i <= 5; $i++)
                                <button type="button"
                                        @click="rating = {{ $i }}"
                                        @mouseenter="hover = {{ $i }}"
                                        @mouseleave="hover = 0"
                                        style="background:none;border:none;cursor:pointer;padding:2px;transition:transform 0.1s;"
                                        :style="rating >= {{ $i }} || hover >= {{ $i }} ? 'transform:scale(1.15)' : ''">
                                    <svg style="width:28px;height:28px;transition:fill 0.15s;"
                                         :style="rating >= {{ $i }} || hover >= {{ $i }} ? 'fill:#d4af37' : 'fill:rgba(255,255,255,0.12)'"
                                         viewBox="0 0 24 24">
                                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                    </svg>
                                </button>
                                @endfor
                            </div>
                            <input type="hidden" name="rating" :value="rating">

                            {{-- Texto de la reseña --}}
                            <textarea name="comment" rows="3" maxlength="500"
                                      placeholder="Comparte tu experiencia con {{ $primerNombre }}... (opcional)"
                                      style="width:100%;resize:none;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:#fff;font-size:0.8rem;padding:10px 12px;outline:none;transition:border-color 0.2s;box-sizing:border-box;"
                                      onfocus="this.style.borderColor='rgba(212,175,55,0.35)'"
                                      onblur="this.style.borderColor='rgba(255,255,255,0.1)'">{{ old('comment') }}</textarea>

                            <button type="submit"
                                    x-show="rating > 0"
                                    style="margin-top:10px;width:100%;padding:10px;border-radius:10px;background:#d4af37;color:#000;font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.15em;border:none;cursor:pointer;transition:opacity 0.2s;"
                                    onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                Publicar reseña
                            </button>
                            <p x-show="!rating" style="font-size:0.65rem;color:rgba(255,255,255,0.3);margin-top:8px;text-align:center;">
                                Selecciona las estrellas para calificar
                            </p>
                        </form>
                    </div>

                    @elseif($alreadyReviewed)
                    <div class="rounded-2xl border border-green-500/15 p-5" style="background:#0d0d0d;">
                        <div class="flex items-center gap-2">
                            <svg style="width:16px;height:16px;fill:#4ade80;flex-shrink:0;" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <p style="font-size:0.72rem;color:rgba(74,222,128,0.8);">Ya dejaste tu reseña. ¡Gracias!</p>
                        </div>
                    </div>

                    @else
                    <div class="rounded-2xl border border-white/6 p-5" style="background:#0d0d0d;">
                        <svg style="width:20px;height:20px;fill:rgba(212,175,55,0.3);margin-bottom:8px;" viewBox="0 0 24 24">
                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                        </svg>
                        <p style="font-size:0.72rem;color:rgba(255,255,255,0.35);line-height:1.5;">
                            Completa una cita con {{ $primerNombre }} para poder dejar tu reseña.
                        </p>
                        <a href="{{ route('client.appointments.create', ['barber_id' => $barber->id]) }}"
                           style="display:inline-block;margin-top:10px;font-size:0.65rem;font-weight:700;color:rgba(212,175,55,0.7);text-decoration:none;text-transform:uppercase;letter-spacing:0.12em;">
                            Agendar cita →
                        </a>
                    </div>
                    @endif
                </div>

                {{-- Columna derecha: lista de reseñas --}}
                <div class="lg:col-span-2">
                    @if($reviews->isEmpty())
                        <div class="flex flex-col items-center justify-center py-16 rounded-2xl border border-dashed border-white/6">
                            <svg style="width:32px;height:32px;fill:rgba(212,175,55,0.15);margin-bottom:12px;" viewBox="0 0 24 24">
                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                            </svg>
                            <p style="font-size:0.7rem;font-weight:700;color:rgba(255,255,255,0.25);text-transform:uppercase;letter-spacing:0.2em;">Sin reseñas aún</p>
                            <p style="font-size:0.7rem;color:rgba(255,255,255,0.18);margin-top:4px;">Sé el primero en valorar a {{ $primerNombre }}</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($reviews as $review)
                                @php
                                    $reviewerName = $review->client?->user?->name ?? 'Cliente';
                                    $reviewerInitials = mb_strtoupper(mb_substr($reviewerName, 0, 2));
                                    $timeAgo = $review->created_at?->diffForHumans() ?? '';
                                @endphp

                                <div class="rounded-2xl border border-white/6 p-4 sm:p-5" style="background:#0d0d0d;">
                                    <div class="flex items-start gap-3">
                                        {{-- Avatar del reviewer --}}
                                        <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,rgba(212,175,55,0.15) 0%,rgba(212,175,55,0.04) 100%);border:1px solid rgba(212,175,55,0.15);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:900;color:#d4af37;flex-shrink:0;">
                                            {{ $reviewerInitials }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                                <div>
                                                    <span style="font-size:0.8rem;font-weight:700;color:#fff;">{{ $reviewerName }}</span>
                                                    <span style="font-size:0.6rem;color:rgba(255,255,255,0.25);margin-left:8px;">· {{ $timeAgo }}</span>
                                                </div>
                                                {{-- Estrellas --}}
                                                <div class="flex gap-0.5">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <svg style="width:13px;height:13px;fill:{{ $i <= $review->rating ? '#d4af37' : 'rgba(255,255,255,0.1)' }};" viewBox="0 0 24 24">
                                                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                                        </svg>
                                                    @endfor
                                                </div>
                                            </div>

                                            @if($review->comment)
                                                <p style="font-size:0.82rem;color:rgba(255,255,255,0.55);margin-top:6px;line-height:1.55;">
                                                    "{{ $review->comment }}"
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if(method_exists($reviews, 'links') && $reviews->hasPages())
                            <div class="mt-5">{{ $reviews->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════
             CTA FINAL
        ════════════════════════════════════════════ --}}
        <div class="rounded-2xl border border-gold/10 p-7 text-center" style="background:rgba(212,175,55,0.02);">
            <p style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.3em;color:rgba(212,175,55,0.6);margin-bottom:8px;">¿Listo para tu próxima transformación?</p>
            <h3 style="font-size:1.25rem;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:-0.02em;">Agenda con {{ $primerNombre }}</h3>
            <p style="font-size:0.82rem;color:rgba(255,255,255,0.4);margin-top:6px;margin-bottom:20px;">Elige tu servicio, fecha y hora. Confirmación inmediata.</p>
            <a href="{{ route('client.appointments.create', ['barber_id' => $barber->id]) }}"
               class="inline-flex items-center gap-2 ui-btn gold-glow"
               style="padding:12px 32px;font-size:0.7rem;letter-spacing:0.2em;text-transform:uppercase;">
                Reservar ahora &rarr;
            </a>
        </div>

    </div>
</x-app-layout>
