@php $db = $settings?->datos_bancarios ?? []; @endphp

{{-- Efectivo --}}
<div x-show="metodoPago === 'efectivo'" x-transition class="rounded-2xl border border-emerald-500/10 bg-emerald-950/10 p-5">
    <div class="flex items-start gap-4">
        <div class="h-10 w-10 rounded-xl bg-emerald-950/40 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-black text-ink">Pago en efectivo</p>
            <p class="text-xs text-muted/80 mt-1 leading-relaxed">El cobro se realiza al concluir tu sesión, directamente en caja. Puedes tener el monto exacto o aproximado listo para agilizar tu salida.</p>
            <div class="mt-3 flex items-center gap-1.5 text-[9px] font-black text-emerald-400 uppercase tracking-wider">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                Sin cargo adicional · Sin necesidad de comprobante
            </div>
        </div>
    </div>
</div>

{{-- Transferencia --}}
<div x-show="metodoPago === 'transferencia'" x-transition class="rounded-2xl border border-blue-500/10 bg-blue-950/10 p-5 space-y-4">
    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-blue-400">Datos para tu transferencia</p>

    @if(!empty($db['clabe']))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <p class="text-[9px] font-black text-muted uppercase tracking-wider mb-1">CLABE Interbancaria</p>
                <div class="flex items-center gap-3 rounded-xl border border-ink/5 bg-black/30 px-4 py-3">
                    <span class="flex-1 font-mono text-base font-black text-ink tracking-widest">{{ $db['clabe'] }}</span>
                    <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $db['clabe'] }}').then(() => { this.textContent = 'Copiado'; setTimeout(() => this.textContent = 'Copiar', 1500) })"
                        class="text-[9px] font-black uppercase text-gold/70 hover:text-gold border border-gold/20 hover:border-gold/40 px-2.5 py-1 rounded-lg transition-all">
                        Copiar
                    </button>
                </div>
            </div>
            @if(!empty($db['banco']))
            <div>
                <p class="text-[9px] font-black text-muted uppercase tracking-wider mb-1">Banco</p>
                <p class="text-sm font-black text-ink">{{ $db['banco'] }}</p>
            </div>
            @endif
            @if(!empty($db['beneficiario']))
            <div>
                <p class="text-[9px] font-black text-muted uppercase tracking-wider mb-1">Beneficiario</p>
                <p class="text-sm font-black text-ink">{{ $db['beneficiario'] }}</p>
            </div>
            @endif
            @if(!empty($db['concepto']))
            <div class="sm:col-span-2">
                <p class="text-[9px] font-black text-muted uppercase tracking-wider mb-1">Concepto / Referencia</p>
                <p class="text-sm font-black text-ink">{{ $db['concepto'] }}</p>
            </div>
            @endif
        </div>
        <p class="text-[10px] text-muted/60 border-t border-ink/5 pt-3 leading-relaxed">
            Realiza la transferencia antes o el día de tu cita y presenta el comprobante al llegar. El barbero validará el pago.
        </p>
    @else
        <div class="rounded-xl border border-dashed border-ink/10 px-4 py-5 text-center">
            <p class="text-xs text-muted/50 italic">Los datos bancarios aún no han sido configurados.</p>
            <p class="text-[9px] text-muted/40 mt-1">Consulta en caja al llegar a tu cita.</p>
        </div>
    @endif
</div>

{{-- Tarjeta Débito / Crédito (BETA) --}}
<div x-show="metodoPago === 'tarjeta'" x-transition class="rounded-2xl border border-amber-500/15 bg-amber-950/[0.06] p-5 space-y-4">
    <div class="flex items-center justify-between">
        <p class="text-sm font-black text-ink">Tarjeta débito / crédito</p>
        <span class="px-2.5 py-1 rounded-full border border-amber-500/30 bg-amber-950/40 text-[8px] font-black uppercase tracking-widest text-amber-400">
            Beta — próximamente
        </span>
    </div>

    <div class="rounded-xl border border-amber-500/10 bg-amber-950/20 px-4 py-3 flex items-start gap-3">
        <svg class="h-4 w-4 text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <p class="text-[10px] text-amber-400/80 leading-relaxed">
            El pago con tarjeta estará disponible muy pronto. Por ahora te pedimos acudir con <strong class="text-amber-300">efectivo</strong> o realizar una <strong class="text-amber-300">transferencia</strong>. No se realizará ningún cargo.
        </p>
    </div>

    <div class="space-y-3 opacity-35 pointer-events-none select-none">
        <div>
            <label class="text-[9px] font-black text-muted uppercase tracking-wider">Número de tarjeta</label>
            <input type="text" disabled placeholder="•••• •••• •••• ••••"
                class="mt-1 w-full rounded-xl border border-ink/5 bg-ink/[0.02] px-4 py-3 font-mono text-sm text-ink placeholder-muted/30">
        </div>
        <div>
            <label class="text-[9px] font-black text-muted uppercase tracking-wider">Titular de la tarjeta</label>
            <input type="text" disabled placeholder="Nombre como aparece en la tarjeta"
                class="mt-1 w-full rounded-xl border border-ink/5 bg-ink/[0.02] px-4 py-3 text-sm text-ink placeholder-muted/30">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-[9px] font-black text-muted uppercase tracking-wider">Vencimiento</label>
                <input type="text" disabled placeholder="MM / AA"
                    class="mt-1 w-full rounded-xl border border-ink/5 bg-ink/[0.02] px-4 py-3 font-mono text-sm text-ink placeholder-muted/30">
            </div>
            <div>
                <label class="text-[9px] font-black text-muted uppercase tracking-wider">CVV</label>
                <input type="text" disabled placeholder="•••"
                    class="mt-1 w-full rounded-xl border border-ink/5 bg-ink/[0.02] px-4 py-3 font-mono text-sm text-ink placeholder-muted/30">
            </div>
        </div>
    </div>
</div>
