<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Comprobantes <span class="text-gold">por Revisar</span></h2>
                <p class="ui-subtitle">Transferencias subidas por clientes, pendientes de aprobación.</p>
            </div>
            <a href="{{ route('payments.index') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                Ver todos los pagos
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        @if($errors->any())
            <div class="rounded-2xl border border-red-500/25 bg-red-500/8 p-4 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        @if($payments->isEmpty())
            <div class="rounded-2xl border border-dashed border-white/10 p-16 text-center">
                <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-sm font-bold text-muted uppercase tracking-widest">No hay comprobantes pendientes</p>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                @foreach($payments as $payment)
                    @php
                        $appt = $payment->appointment;
                        $esperado = (float) ($appt?->service?->precio ?? 0);
                        $subido = (float) $payment->monto;
                        $coincide = abs($esperado - $subido) < 0.01;
                    @endphp
                    <div class="ui-card-premium overflow-hidden">
                        <div class="p-5 border-b border-white/6 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-black text-white">{{ $appt?->client?->user?->name ?? 'Cliente' }}</p>
                                <p class="text-[10px] uppercase tracking-widest text-gold font-black">{{ $appt?->service?->nombre ?? 'Servicio' }}</p>
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-muted">
                                {{ $payment->created_at?->translatedFormat('d M, H:i') }}
                            </span>
                        </div>

                        <div class="p-5 space-y-4">
                            @if($payment->comprobante_cliente)
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($payment->comprobante_cliente) }}" target="_blank"
                                   class="block rounded-xl border border-white/10 overflow-hidden bg-black/40">
                                    @if(str_ends_with($payment->comprobante_cliente, '.pdf'))
                                        <div class="p-6 text-center text-xs text-muted">📄 Ver comprobante PDF</div>
                                    @else
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($payment->comprobante_cliente) }}" alt="Comprobante" class="w-full max-h-80 object-contain">
                                    @endif
                                </a>
                            @endif

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-muted">Monto esperado</p>
                                    <p class="font-black text-white">${{ number_format($esperado, 2) }}</p>
                                </div>
                                <div class="rounded-xl border p-3 {{ $coincide ? 'border-emerald-500/25 bg-emerald-500/8' : 'border-amber-500/25 bg-amber-500/8' }}">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-muted">Monto de la cita</p>
                                    <p class="font-black {{ $coincide ? 'text-emerald-400' : 'text-amber-400' }}">${{ number_format($subido, 2) }}</p>
                                </div>
                            </div>

                            @if($payment->ocr_texto)
                                <div class="rounded-xl border border-blue-500/20 bg-blue-500/5 p-3">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-300 mb-1">Texto detectado (OCR)</p>
                                    <p class="text-[11px] text-white/70 whitespace-pre-line">{{ Str::limit($payment->ocr_texto, 300) }}</p>
                                    @if($payment->ocr_monto_detectado)
                                        <p class="text-[10px] text-blue-300 mt-1">Monto sugerido: ${{ number_format($payment->ocr_monto_detectado, 2) }}</p>
                                    @endif
                                </div>
                            @endif

                            <div class="flex items-center gap-3 pt-2">
                                <form action="{{ route('payments.approve', $payment) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-500 text-black text-[11px] font-black uppercase tracking-widest hover:bg-emerald-400 transition-all">
                                        Aprobar
                                    </button>
                                </form>
                                <div x-data="{ open: false }" class="flex-1">
                                    <button type="button" @click="open = true" class="w-full py-2.5 rounded-xl border border-red-500/30 bg-red-500/10 text-[11px] font-black uppercase tracking-widest text-red-300 hover:bg-red-500/20 transition-all">
                                        Rechazar
                                    </button>
                                    <div x-show="open" x-cloak @click.outside="open = false" class="mt-3 rounded-xl border border-white/10 bg-[#111] p-3 space-y-2">
                                        <form action="{{ route('payments.reject', $payment) }}" method="POST">
                                            @csrf
                                            <textarea name="motivo_rechazo" required maxlength="500" placeholder="Motivo del rechazo..." class="ui-input text-xs" rows="2"></textarea>
                                            <button type="submit" class="mt-2 w-full py-2 rounded-lg bg-red-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-red-400 transition-all">
                                                Confirmar rechazo
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
