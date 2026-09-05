@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Reseñas de <span class="text-gold">Clientes</span></h2>
                <p class="ui-subtitle">Calificaciones y comentarios dejados por clientes sobre cada barbero.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-ink/8 bg-card p-4 text-center">
                <p class="text-3xl font-black text-ink">{{ $stats['total'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Total reseñas</p>
            </div>
            <div class="rounded-2xl border border-gold/20 bg-gold/5 p-4 text-center">
                <p class="text-3xl font-black text-gold">{{ number_format($stats['promedio'], 1) }}★</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-gold/70 mt-1">Promedio general</p>
            </div>
            <div class="rounded-2xl border {{ $stats['bajas'] > 0 ? 'border-red-500/25 bg-red-500/5' : 'border-ink/8 bg-card' }} p-4 text-center">
                <p class="text-3xl font-black {{ $stats['bajas'] > 0 ? 'text-red-400' : 'text-ink' }}">{{ $stats['bajas'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider {{ $stats['bajas'] > 0 ? 'text-red-400/70' : 'text-muted' }} mt-1">1-2★ (atención)</p>
            </div>
        </section>

        {{-- ── FILTROS ─────────────────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-ink/6" @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-ink uppercase tracking-widest">Filtros</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('reviews.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label">Barbero</label>
                            <select name="barber_id" class="ui-input mt-1">
                                <option value="">Todos</option>
                                @foreach($barbers as $b)
                                    <option value="{{ $b->id }}" @selected(($filters['barber_id'] ?? '') == $b->id)>{{ $b->user?->name ?? $b->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Calificación</label>
                            <select name="rating" class="ui-input mt-1">
                                <option value="">Todas</option>
                                @foreach([5, 4, 3, 2, 1] as $r)
                                    <option value="{{ $r }}" @selected(($filters['rating'] ?? '') == $r)>{{ $r }} estrella{{ $r !== 1 ? 's' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('reviews.index') }}" class="flex items-center gap-1.5 rounded-xl border border-ink/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-ink transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        {{-- ── LISTA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $reviews->total() }} reseña{{ $reviews->total() !== 1 ? 's' : '' }}</p>
            </div>

            <div class="space-y-3">
                @forelse($reviews as $review)
                    @php $isLow = $review->rating <= 2; @endphp
                    <div class="rounded-2xl border {{ $isLow ? 'border-red-500/25 bg-red-500/5' : 'border-ink/8 bg-card' }} p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-ink text-sm">{{ $review->barber?->user?->name ?? $review->barber?->nombre ?? 'Barbero eliminado' }}</p>
                                <p class="text-[11px] text-muted mt-0.5">Reseñado por {{ $review->client?->user?->name ?? 'Cliente eliminado' }} · {{ optional($review->created_at)->translatedFormat('d M, Y') }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-black
                                {{ $isLow ? 'border-red-500/30 bg-red-500/10 text-red-400' : 'border-gold/25 bg-gold/8 text-gold' }}">
                                {{ $review->rating }}★
                            </span>
                        </div>
                        @if($review->comment)
                            <p class="text-sm text-ink/80 mt-3 italic">"{{ $review->comment }}"</p>
                        @else
                            <p class="text-sm text-muted/50 mt-3 italic">Sin comentario.</p>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink/10 py-20 text-center">
                        <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin reseñas registradas</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">{{ $reviews->links() }}</div>
        </section>
    </div>
</x-app-layout>
