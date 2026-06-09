<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Nuestros <span class="text-gold">Maestros</span></h2>
                <p class="ui-subtitle">Conoce a los artistas que harán que luzcas increíble.</p>
            </div>
            <a href="{{ route('client.appointments.create') }}" class="ui-btn shadow-gold/10">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Agendar Cita
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            @if($barbers->isEmpty())
                <div class="py-20 text-center rounded-3xl border border-dashed border-white/5">
                    <p class="text-muted font-bold uppercase tracking-widest text-sm">No hay barberos disponibles</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($barbers as $barber)
                        @php
                            $foto = $barber->foto
                                ? (str_starts_with($barber->foto, 'http') ? $barber->foto : Storage::url($barber->foto))
                                : null;
                            $initials = mb_strtoupper(mb_substr($barber->user?->name ?? '?', 0, 2));
                            $especialidades = array_filter(array_map('trim', explode(',', $barber->especialidades ?? $barber->especialidad ?? '')));
                        @endphp

                        <a href="{{ route('client.barberos.show', $barber) }}"
                           class="group block rounded-3xl border border-white/5 bg-white/[0.02] overflow-hidden transition-all hover:border-gold/30 hover:shadow-xl hover:shadow-gold/5 hover:-translate-y-1">

                            <!-- Photo / Avatar -->
                            <div class="relative h-56 overflow-hidden">
                                @if($foto)
                                    <img src="{{ $foto }}" alt="{{ $barber->user?->name }}"
                                         class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105">
                                @else
                                    <div class="w-full h-full bg-gradient-to-b from-gold/[0.08] to-black/60 flex items-center justify-center relative">
                                        <span class="absolute text-[8rem] font-black text-white/[0.03] select-none leading-none">{{ $initials }}</span>
                                        <div class="relative h-24 w-24 rounded-3xl bg-black/40 border border-gold/20 backdrop-blur-sm flex items-center justify-center text-3xl font-black text-gold group-hover:bg-gold group-hover:text-black transition-all duration-300">
                                            {{ $initials }}
                                        </div>
                                    </div>
                                @endif

                                <!-- Overlay gradient -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>

                                <!-- Specialties on photo -->
                                @if(!empty($especialidades))
                                <div class="absolute bottom-3 left-3 flex flex-wrap gap-1.5">
                                    @foreach(array_slice($especialidades, 0, 2) as $esp)
                                        <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider bg-gold/20 border border-gold/30 text-gold backdrop-blur-sm">
                                            {{ $esp }}
                                        </span>
                                    @endforeach
                                </div>
                                @endif

                                <!-- Citas count badge -->
                                @if(($barber->citas_completadas ?? 0) > 0)
                                <div class="absolute top-3 right-3 flex items-center gap-1 rounded-full bg-black/50 border border-white/10 backdrop-blur-sm px-2.5 py-1">
                                    <svg class="h-3 w-3 text-gold/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-[9px] font-black text-white/70">{{ $barber->citas_completadas }} citas</span>
                                </div>
                                @endif
                            </div>

                            <!-- Info -->
                            <div class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-black text-white uppercase tracking-tight group-hover:text-gold transition-colors">
                                            {{ $barber->user?->name }}
                                        </h3>
                                        <p class="text-[9px] font-bold text-muted uppercase tracking-[0.2em] mt-0.5">Master Groomer</p>
                                    </div>
                                    <div class="h-7 w-7 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center group-hover:bg-gold group-hover:border-gold transition-all flex-shrink-0">
                                        <svg class="h-3.5 w-3.5 text-muted group-hover:text-black transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </div>

                                @if($barber->descripcion)
                                    <p class="mt-3 text-xs text-muted/70 line-clamp-2 leading-relaxed">{{ $barber->descripcion }}</p>
                                @endif

                                <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                                    <span class="text-[9px] font-bold text-muted uppercase tracking-widest">Ver perfil completo</span>
                                    <svg class="h-3 w-3 text-gold/50 group-hover:text-gold group-hover:translate-x-0.5 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
