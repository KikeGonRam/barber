<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Historial de <span class="text-gold">Sorteos</span></h2>
                <p class="ui-subtitle">Ganadores del sorteo mensual y estado de redención de su premio.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-ink/8 bg-card p-4 text-center">
                <p class="text-3xl font-black text-ink">{{ $stats['total'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mt-1">Total sorteos</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-center">
                <p class="text-3xl font-black text-emerald-400">{{ $stats['reclamados'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400/70 mt-1">Reclamados</p>
            </div>
            <div class="rounded-2xl border border-fuchsia-400/20 bg-fuchsia-400/5 p-4 text-center">
                <p class="text-3xl font-black text-fuchsia-400">{{ $stats['vigentes'] }}</p>
                <p class="text-[10px] font-black uppercase tracking-wider text-fuchsia-400/70 mt-1">Vigentes sin reclamar</p>
            </div>
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Ganador</th>
                            <th>Nivel</th>
                            <th>Premio</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $result)
                            <tr>
                                <td class="font-bold text-ink text-sm">{{ $result->mes }}</td>
                                <td class="text-ink/80 text-sm">{{ $result->client?->user?->name ?? 'Cliente eliminado' }}</td>
                                <td>
                                    <span class="inline-flex items-center rounded-full border border-ink/10 bg-ink/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-muted">
                                        {{ \App\Services\Loyalty\LoyaltyService::LEVEL_LABELS[$result->nivel_ganador] ?? $result->nivel_ganador }}
                                    </span>
                                </td>
                                <td class="text-ink/80 text-sm">{{ $result->premio }}</td>
                                <td>
                                    @if($result->isClaimed())
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-400">
                                            Reclamado {{ $result->reclamado_en->format('d/m/Y') }}
                                        </span>
                                    @elseif($result->isExpired())
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-red-500/25 bg-red-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-red-400">
                                            Caducó {{ optional($result->vence_en)->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-fuchsia-400/25 bg-fuchsia-400/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-fuchsia-400">
                                            Vigente hasta {{ optional($result->vence_en)->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin sorteos registrados</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($results as $result)
                    <div class="rounded-2xl border border-ink/8 bg-card p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <p class="font-bold text-ink text-sm">{{ $result->client?->user?->name ?? 'Cliente eliminado' }}</p>
                                <p class="text-[10px] text-muted">{{ $result->mes }} · {{ $result->premio }}</p>
                            </div>
                        </div>
                        @if($result->isClaimed())
                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-400">
                                Reclamado {{ $result->reclamado_en->format('d/m/Y') }}
                            </span>
                        @elseif($result->isExpired())
                            <span class="inline-flex items-center gap-1 rounded-full border border-red-500/25 bg-red-500/10 px-2 py-0.5 text-[9px] font-black uppercase text-red-400">
                                Caducó {{ optional($result->vence_en)->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full border border-fuchsia-400/25 bg-fuchsia-400/10 px-2 py-0.5 text-[9px] font-black uppercase text-fuchsia-400">
                                Vigente hasta {{ optional($result->vence_en)->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink/10 p-10 text-center"><p class="text-sm text-muted">Sin sorteos.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $results->links() }}</div>
        </section>
    </div>
</x-app-layout>
