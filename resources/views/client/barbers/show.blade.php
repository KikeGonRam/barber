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

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-8">

            @php
                $foto = $barber->foto
                    ? (str_starts_with($barber->foto, 'http') ? $barber->foto : Storage::url($barber->foto))
                    : null;
                $initials = mb_strtoupper(mb_substr($barber->user?->name ?? '?', 0, 2));
                $especialidades = array_filter(array_map('trim', explode(',', $barber->especialidades ?? $barber->especialidad ?? '')));
            @endphp

            <!-- Hero Card -->
            <div class="relative overflow-hidden rounded-3xl border border-white/5 bg-white/[0.02]">

                <!-- Background blur from photo -->
                @if($foto)
                <div class="absolute inset-0 overflow-hidden">
                    <img src="{{ $foto }}" alt="" class="w-full h-full object-cover object-top scale-110 blur-2xl opacity-10">
                    <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/70 to-black/95"></div>
                </div>
                @else
                <div class="absolute inset-0 bg-gradient-to-br from-gold/[0.04] to-transparent"></div>
                @endif

                <div class="relative p-8">
                    <div class="flex flex-col sm:flex-row gap-8 items-start">

                        <!-- Avatar -->
                        <div class="flex-shrink-0">
                            @if($foto)
                                <img src="{{ $foto }}" alt="{{ $barber->user?->name }}"
                                     class="h-36 w-36 rounded-3xl object-cover object-top border-2 border-gold/30 shadow-2xl shadow-gold/10">
                            @else
                                <div class="h-36 w-36 rounded-3xl bg-gradient-to-br from-gold/20 to-gold/5 border border-gold/20 flex items-center justify-center text-4xl font-black text-gold shadow-2xl shadow-gold/10">
                                    {{ $initials }}
                                </div>
                            @endif

                            <!-- Availability badge -->
                            <div class="mt-3 flex items-center justify-center gap-1.5 rounded-xl px-3 py-1.5 border text-[9px] font-black uppercase tracking-wider
                                {{ $disponibleHoy ? 'border-green-500/30 bg-green-950/30 text-green-400' : 'border-white/10 bg-white/5 text-muted' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $disponibleHoy ? 'bg-green-400 animate-pulse' : 'bg-muted' }}"></span>
                                {{ $disponibleHoy ? 'Disponible hoy' : 'No disponible hoy' }}
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h1 class="text-3xl font-black text-white uppercase tracking-tighter">{{ $barber->user?->name }}</h1>
                                    <p class="text-[10px] font-bold text-gold/60 uppercase tracking-[0.3em] mt-1">Master Groomer · UrbanBlade</p>
                                </div>

                                <!-- Stats -->
                                <div class="flex items-center gap-5">
                                    <div class="text-center">
                                        <p class="text-2xl font-black text-gold">{{ $citasCompletadas }}</p>
                                        <p class="text-[8px] font-black text-muted uppercase tracking-widest">Citas</p>
                                    </div>
                                    @if($avgRating)
                                    <div class="text-center">
                                        <p class="text-2xl font-black text-gold">{{ $avgRating }}</p>
                                        <p class="text-[8px] font-black text-muted uppercase tracking-widest">Rating</p>
                                    </div>
                                    @endif
                                    <div class="text-center">
                                        <p class="text-2xl font-black text-gold">{{ $yearsExp }}+</p>
                                        <p class="text-[8px] font-black text-muted uppercase tracking-widest">Años</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Specialties -->
                            @if(!empty($especialidades))
                                <div class="mt-5 flex flex-wrap gap-2">
                                    @foreach($especialidades as $esp)
                                        <span class="px-3 py-1 rounded-full border border-gold/20 bg-gold/[0.06] text-[9px] font-black uppercase tracking-wider text-gold/80">
                                            {{ $esp }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Description -->
                            @if($barber->descripcion)
                                <p class="mt-5 text-sm text-muted/80 leading-relaxed max-w-xl">{{ $barber->descripcion }}</p>
                            @endif

                            <!-- CTA Button -->
                            <div class="mt-6">
                                <a href="{{ route('client.appointments.create', ['barber_id' => $barber->id]) }}"
                                   class="inline-flex items-center gap-2 ui-btn px-8 py-3.5 text-[11px] uppercase tracking-[0.2em] gold-glow">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Agendar cita con {{ explode(' ', $barber->user?->name ?? 'este barbero')[0] }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Portfolio -->
            @if($works->isNotEmpty())
            <section class="space-y-5">
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-white/5"></div>
                    <span class="text-[9px] font-black uppercase tracking-[0.35em] text-gold">Portfolio</span>
                    <div class="h-px flex-1 bg-white/5"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($works as $work)
                        @php
                            $img = $work->images->first();
                            $imgUrl = $img ? (str_starts_with($img->image, 'http') ? $img->image : Storage::url($img->image)) : null;
                            $reactCount = $work->reactions->count();
                            $commentCount = $work->comments->count();
                        @endphp
                        <div class="group relative rounded-2xl overflow-hidden border border-white/5 bg-white/[0.02] aspect-square">
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" alt="{{ $work->title }}"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-gold/[0.04] to-transparent flex items-center justify-center">
                                    <svg class="h-8 w-8 text-gold/10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif

                            <!-- Hover overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex flex-col justify-end p-3">
                                <p class="text-[10px] font-black text-white uppercase leading-tight line-clamp-1">{{ $work->title }}</p>
                                @if($work->work_date)
                                    <p class="text-[8px] text-muted mt-0.5">{{ \Carbon\Carbon::parse($work->work_date)->translatedFormat('d M Y') }}</p>
                                @endif
                                <div class="flex items-center gap-3 mt-2">
                                    @if($reactCount > 0)
                                    <span class="flex items-center gap-1 text-[9px] text-white/60">
                                        <svg class="h-3 w-3 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                        {{ $reactCount }}
                                    </span>
                                    @endif
                                    @if($commentCount > 0)
                                    <span class="flex items-center gap-1 text-[9px] text-white/60">
                                        <svg class="h-3 w-3 text-gold/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                        {{ $commentCount }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            @else
                <div class="py-12 text-center rounded-2xl border border-dashed border-white/5">
                    <p class="text-xs text-muted/50 uppercase tracking-widest">Este barbero aún no tiene trabajos publicados</p>
                </div>
            @endif

            <!-- Second CTA -->
            <div class="rounded-2xl border border-gold/10 bg-gold/[0.02] p-8 text-center">
                <p class="text-[9px] font-black uppercase tracking-[0.35em] text-gold mb-3">¿Listo para tu próxima transformación?</p>
                <h3 class="text-xl font-black text-white uppercase">Agenda tu sesión con {{ explode(' ', $barber->user?->name ?? 'este barbero')[0] }}</h3>
                <p class="text-sm text-muted mt-2 mb-6">Elige tu servicio, fecha y hora. En minutos tendrás tu cita confirmada.</p>
                <a href="{{ route('client.appointments.create', ['barber_id' => $barber->id]) }}"
                   class="inline-flex items-center gap-2 ui-btn px-10 py-4 text-[11px] uppercase tracking-[0.2em] gold-glow">
                    Reservar ahora &rarr;
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
