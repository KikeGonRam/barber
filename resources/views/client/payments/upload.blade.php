<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="ui-title">Subir <span class="text-gold">Comprobante</span></h2>
            <p class="ui-subtitle">Transferencia bancaria — sube la foto o PDF de tu comprobante.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8 space-y-6">

            @if($ultimoRechazo)
                <div class="rounded-2xl border border-red-500/25 bg-red-500/8 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-red-300">Comprobante anterior rechazado</p>
                    <p class="text-sm text-white/80 mt-1">{{ $ultimoRechazo->motivo_rechazo ?? 'No cumplió con lo esperado.' }}</p>
                </div>
            @endif

            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                <p class="text-sm font-black text-white uppercase leading-tight">{{ $appointment->service?->nombre ?? 'Servicio' }}</p>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted font-bold mt-1">
                    @if($appointment->barber?->user)
                        <span>{{ $appointment->barber->user->name }}</span>
                    @endif
                    @if($appointment->fecha)
                        <span>{{ $appointment->fecha->translatedFormat('d M, Y') }}</span>
                    @endif
                    <span class="text-gold">${{ number_format($appointment->service?->precio ?? 0, 2) }}</span>
                </div>
            </div>

            @if($errors->any())
                <div class="rounded-2xl border border-red-500/25 bg-red-500/8 p-4 text-sm text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('client.payments.store', $appointment) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="ui-label">Comprobante (imagen o PDF, máx. 10MB)</label>
                    <input type="file" name="comprobante" accept="image/*,application/pdf" required class="ui-input mt-1">
                </div>
                <p class="text-[10px] text-muted/60">
                    Tu comprobante será revisado por el equipo antes de confirmar el pago. Te avisaremos en cuanto se valide.
                </p>
                <button type="submit" class="ui-btn w-full justify-center">
                    Enviar comprobante &rarr;
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
