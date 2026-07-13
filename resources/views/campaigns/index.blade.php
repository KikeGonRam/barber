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
            modo: 'ahora',
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

                {{-- Envío: ahora o programado --}}
                <div class="pt-2 border-t border-white/5">
                    <label class="ui-label">Envío</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2.5 rounded-xl border px-4 py-3 cursor-pointer transition-all"
                               :class="modo === 'ahora' ? 'border-gold/50 bg-gold/5' : 'border-white/10'">
                            <input type="radio" name="modo" value="ahora" x-model="modo" class="accent-gold">
                            <span class="text-xs font-bold text-white">Enviar ahora</span>
                        </label>
                        <label class="flex items-center gap-2.5 rounded-xl border px-4 py-3 cursor-pointer transition-all"
                               :class="modo === 'programar' ? 'border-gold/50 bg-gold/5' : 'border-white/10'">
                            <input type="radio" name="modo" value="programar" x-model="modo" class="accent-gold">
                            <span class="text-xs font-bold text-white">Programar</span>
                        </label>
                    </div>
                    <div x-show="modo === 'programar'" x-cloak class="mt-3">
                        <input type="datetime-local" name="programada_para" value="{{ old('programada_para') }}"
                               :required="modo === 'programar'" class="ui-input w-full">
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2 border-t border-white/5">
                    <p class="text-[11px] text-muted">
                        Destinatarios del segmento:
                        <span class="text-gold font-black text-base" x-text="recipientes"></span>
                        <span class="block text-[10px] text-muted/70 mt-0.5">Quienes desactivaron promociones se omiten automáticamente.</span>
                    </p>
                    <button type="submit" class="ui-btn px-8 py-3 text-[11px] tracking-widest shrink-0">
                        <span x-text="modo === 'programar' ? 'Programar campaña' : 'Enviar campaña'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Historial --}}
        <div>
            <div class="ui-card-premium p-6">
                <h3 class="text-[11px] font-black uppercase tracking-widest text-muted mb-4">Últimas campañas</h3>
                @forelse($campaigns as $c)
                    @php $programada = $c->estado === 'programada'; @endphp
                    <div class="py-3 border-b border-white/5 last:border-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-white line-clamp-1">{{ $c->titulo }}</p>
                            <span class="shrink-0 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border
                                {{ $programada ? 'text-amber-300 border-amber-500/30 bg-amber-500/10' : 'text-emerald-300 border-emerald-500/25 bg-emerald-500/10' }}">
                                {{ $programada ? 'Programada' : 'Enviada' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-1 text-[10px] font-bold uppercase tracking-wider text-muted">
                            <span class="text-gold">{{ $c->segmento === 'todos' ? 'Todos' : ($levels[$c->segmento] ?? $c->segmento) }}</span>
                            <span>·</span>
                            @if($programada)
                                <span>{{ optional($c->programada_para)->format('d M, H:i') }}</span>
                            @else
                                <span>{{ optional($c->enviada_en ?? $c->created_at)->format('d M') }}</span>
                            @endif
                        </div>
                        @unless($programada)
                            @php $opens = $c->opensCount(); $clicks = $c->clicksCount(); @endphp
                            <div class="grid grid-cols-3 gap-2 mt-2.5">
                                <div class="rounded-lg bg-white/[0.03] border border-white/5 px-2 py-1.5 text-center">
                                    <p class="text-sm font-black text-white leading-none">{{ $c->destinatarios }}</p>
                                    <p class="text-[8px] font-black uppercase tracking-wider text-muted mt-1">Enviados</p>
                                </div>
                                <div class="rounded-lg bg-white/[0.03] border border-white/5 px-2 py-1.5 text-center">
                                    <p class="text-sm font-black text-white leading-none">{{ $opens }} <span class="text-[9px] text-gold">{{ $c->rate($opens) }}%</span></p>
                                    <p class="text-[8px] font-black uppercase tracking-wider text-muted mt-1">Aperturas</p>
                                </div>
                                <div class="rounded-lg bg-white/[0.03] border border-white/5 px-2 py-1.5 text-center">
                                    <p class="text-sm font-black text-white leading-none">{{ $clicks }} <span class="text-[9px] text-gold">{{ $c->rate($clicks) }}%</span></p>
                                    <p class="text-[8px] font-black uppercase tracking-wider text-muted mt-1">Clics</p>
                                </div>
                            </div>
                        @endunless
                    </div>
                @empty
                    <p class="text-xs text-muted/70 italic py-6 text-center">Aún no has enviado campañas.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
