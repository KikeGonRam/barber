<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="ui-title">Campañas de <span class="text-gold">marketing</span></h2>
            <p class="ui-subtitle">Compón y envía promociones a tus clientes, segmentadas por nivel de lealtad.</p>
        </div>
    </x-slot>

    <div class="py-4 grid grid-cols-1 lg:grid-cols-3 gap-6"
         x-data="{
            segmento: 'todos',
            counts: {{ Illuminate\Support\Js::from($segmentCounts) }},
            get recipientes() { return this.counts[this.segmento] ?? 0; }
         }">

        {{-- Compositor --}}
        <div class="lg:col-span-2">
            @if(session('status'))
                <div class="mb-6 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">
                    {{ session('status') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-6 rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('campaigns.send') }}"
                  onsubmit="return confirm('¿Enviar esta campaña a los clientes del segmento seleccionado?')"
                  class="ui-card-premium p-6 sm:p-8 space-y-5">
                @csrf

                <div>
                    <label class="ui-label">Título / Asunto</label>
                    <input type="text" name="titulo" value="{{ old('titulo') }}" required maxlength="150"
                           placeholder="Ej: 20% en tu próximo Fade este fin de semana"
                           class="ui-input w-full">
                </div>

                <div>
                    <label class="ui-label">Mensaje</label>
                    <textarea name="cuerpo" required maxlength="2000" rows="5"
                              placeholder="Escribe el cuerpo de la promoción..."
                              class="ui-input w-full resize-y">{{ old('cuerpo') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="ui-label">Texto del botón</label>
                        <input type="text" name="cta_label" value="{{ old('cta_label', 'Reservar ahora') }}" maxlength="40"
                               class="ui-input w-full">
                    </div>
                    <div>
                        <label class="ui-label">Enlace del botón (opcional)</label>
                        <input type="url" name="cta_url" value="{{ old('cta_url') }}" maxlength="300"
                               placeholder="Por defecto: catálogo de servicios"
                               class="ui-input w-full">
                    </div>
                </div>

                <div>
                    <label class="ui-label">Segmento (nivel de lealtad)</label>
                    <select name="segmento" x-model="segmento" class="ui-input w-full">
                        <option value="todos">Todos los clientes ({{ $segmentCounts['todos'] ?? 0 }})</option>
                        @foreach($levels as $key => $label)
                            <option value="{{ $key }}">{{ $label }} ({{ $segmentCounts[$key] ?? 0 }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2 border-t border-white/5">
                    <p class="text-[11px] text-muted">
                        Destinatarios del segmento:
                        <span class="text-gold font-black text-base" x-text="recipientes"></span>
                        <span class="block text-[10px] text-muted/70 mt-0.5">Quienes desactivaron promociones se omiten automáticamente.</span>
                    </p>
                    <button type="submit" class="ui-btn px-8 py-3 text-[11px] tracking-widest shrink-0">Enviar campaña</button>
                </div>
            </form>
        </div>

        {{-- Historial --}}
        <div>
            <div class="ui-card-premium p-6">
                <h3 class="text-[11px] font-black uppercase tracking-widest text-muted mb-4">Últimas campañas</h3>
                @forelse($campaigns as $c)
                    <div class="py-3 border-b border-white/5 last:border-0">
                        <p class="text-sm font-bold text-white line-clamp-1">{{ $c->titulo }}</p>
                        <div class="flex items-center gap-2 mt-1 text-[10px] font-bold uppercase tracking-wider text-muted">
                            <span class="text-gold">{{ $c->segmento === 'todos' ? 'Todos' : ($levels[$c->segmento] ?? $c->segmento) }}</span>
                            <span>·</span>
                            <span>{{ $c->destinatarios }} dest.</span>
                            <span>·</span>
                            <span>{{ optional($c->created_at)->format('d M') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-muted/70 italic py-6 text-center">Aún no has enviado campañas.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
