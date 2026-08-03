@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Equipo de <span class="text-gold">Barberos</span></h2>
                <p class="ui-subtitle">Gestiona el personal, especialidades y disponibilidad.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4 text-center">
                <p class="text-3xl font-black text-white">{{ $stats['total'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Total</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-center">
                <p class="text-3xl font-black text-emerald-400">{{ $stats['activos'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400/70 mt-1">Activos</p>
            </div>
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4 text-center">
                <p class="text-3xl font-black text-muted">{{ $stats['inactivos'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Inactivos</p>
            </div>
        </section>

        {{-- ── FILTROS ─────────────────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-white/6" @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-white uppercase tracking-widest">Filtros</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('barbers.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Nombre o email...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Especialidad</label>
                            <input type="text" name="especialidad" value="{{ $filters['especialidad'] ?? '' }}" class="ui-input mt-1" placeholder="ej: fade, barba...">
                        </div>
                        <div>
                            <label class="ui-label">Estado</label>
                            <select name="activo" class="ui-input mt-1">
                                <option value="">Todos</option>
                                <option value="1" @selected(($filters['activo'] ?? '') === '1')>Activos</option>
                                <option value="0" @selected(($filters['activo'] ?? '') === '0')>Inactivos</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('barbers.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
            @if(count($activeFilters) > 0)
                <div class="flex flex-wrap gap-2 px-6 pb-4">
                    @foreach($activeFilters as $key => $val)
                        @php $labels = ['q'=>'Búsqueda','activo'=>'Estado','especialidad'=>'Especialidad']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}: {{ $key === 'activo' ? ($val === '1' ? 'Activo' : 'Inactivo') : $val }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── GRID DE BARBEROS ─────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $barbers->total() }} barbero{{ $barbers->total() !== 1 ? 's' : '' }}</p>
            </div>

            {{-- Desktop: Cards en grid ─────────────────── --}}
            <div class="hidden md:grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @php
                    $barberPhotos = [
                        'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=400&q=80&fit=crop',
                        'https://images.unsplash.com/photo-1592647420148-bfcc177e2117?w=400&q=80&fit=crop',
                        'https://images.unsplash.com/photo-1519345182560-3f2917c472ef?w=400&q=80&fit=crop',
                        'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=400&q=80&fit=crop',
                        'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&q=80&fit=crop',
                        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&q=80&fit=crop',
                    ];
                @endphp
                @forelse($barbers as $barber)
                    @php $fallbackImg = $barberPhotos[abs(crc32($barber->id ?? 'x')) % count($barberPhotos)]; @endphp
                    <div class="group rounded-2xl border border-white/8 bg-[#111] overflow-hidden hover:border-gold/30 hover:shadow-[0_12px_40px_rgba(0,0,0,0.5)] transition-all duration-400">
                        {{-- Foto --}}
                        <div class="relative h-48 bg-[#181818] overflow-hidden">
                            @if($barber->foto)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}"
                                     class="h-full w-full object-cover grayscale group-hover:grayscale-0 group-hover:scale-105 transition-all duration-600"
                                     loading="lazy"
                                     alt="Foto de {{ $barber->user?->name }}">
                            @else
                                <img src="{{ $fallbackImg }}"
                                     class="h-full w-full object-cover object-top grayscale group-hover:grayscale-0 group-hover:scale-105 transition-all duration-600"
                                     loading="lazy"
                                     alt="{{ $barber->user?->name }}">
                            @endif
                            {{-- Estado badge --}}
                            <div class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider backdrop-blur-sm
                                    {{ $barber->activo ? 'border-emerald-500/30 bg-emerald-500/20 text-emerald-300' : 'border-white/10 bg-black/40 text-muted' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $barber->activo ? 'bg-emerald-400 animate-pulse' : 'bg-white/30' }}"></span>
                                    {{ $barber->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>

                        {{-- Info --}}
                        <div class="p-5">
                            <h3 class="font-black text-white text-base">{{ $barber->user?->name ?? 'Sin usuario' }}</h3>
                            <p class="text-[10px] text-muted mt-0.5">{{ $barber->user?->email ?: '—' }}</p>

                            @if($barber->especialidades)
                                <div class="flex flex-wrap gap-1.5 mt-3">
                                    @foreach(explode(',', $barber->especialidades) as $esp)
                                        <span class="text-[9px] font-black border border-gold/20 bg-gold/8 text-gold rounded-full px-2 py-0.5 uppercase tracking-wider">
                                            {{ trim($esp) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-[10px] text-muted/50 italic mt-3">Sin especialidades</p>
                            @endif

                            {{-- Citas del mes --}}
                            <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold text-muted uppercase tracking-wider">Citas este mes</p>
                                    <p class="text-xl font-black text-white">{{ $barber->citas_mes ?? 0 }}</p>
                                </div>
                                @if($barber->slug)
                                <div class="flex gap-2">
                                    <a href="{{ route('barbers.performance', $barber) }}"
                                       class="flex items-center gap-1 rounded-xl border border-gold/20 bg-gold/8 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        Stats
                                    </a>
                                    <a href="{{ route('barbers.edit', $barber) }}"
                                       class="flex items-center gap-1 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold hover:border-gold/30 transition-all">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Editar
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-4 rounded-2xl border border-dashed border-white/10 py-20 text-center">
                        <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin barberos registrados</p>
                    </div>
                @endforelse
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($barbers as $barber)
                    <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-gold/20 to-gold/5 border border-gold/15 flex items-center justify-center text-base font-black text-gold shrink-0">
                                {{ strtoupper(mb_substr($barber->user?->name ?? 'B', 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-white text-sm">{{ $barber->user?->name }}</p>
                                <p class="text-[10px] text-muted">{{ $barber->user?->email ?: '—' }}</p>
                            </div>
                            <span class="text-[9px] font-black border rounded-full px-2 py-0.5 {{ $barber->activo ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-white/10 text-muted' }}">
                                {{ $barber->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/5 pt-3">
                            <div>
                                <p class="text-[9px] text-muted uppercase tracking-wider">Especialidades</p>
                                <p class="text-xs text-white mt-0.5">{{ $barber->especialidades ?: 'General' }}</p>
                            </div>
                            @if($barber->slug)
                            <a href="{{ route('barbers.edit', $barber) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Gestionar</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center"><p class="text-sm text-muted">Sin barberos.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $barbers->links() }}</div>
        </section>
    </div>
</x-app-layout>
