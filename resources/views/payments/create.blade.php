<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Registro de <span class="text-gold">Cobro</span></h2>
                <p class="ui-subtitle">Finaliza el servicio y procesa el pago de forma segura.</p>
            </div>
            <a href="{{ route('payments.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-ink transition">
                &larr; Ver historial de pagos
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section
                id="paymentForm"
                x-data="{
                    monto: {{ old('monto', 0) }},
                    propina: {{ old('propina', 0) }},
                    metodo: '{{ old('metodo_pago', 'efectivo') }}',
                    stripePaymentId: '',
                    nivelPct: 0,
                    nivelLabel: '',
                    puntosDisponibles: 0,
                    puntosCanjear: {{ old('puntos_canjeados', 0) }},
                    get montoConNivel() { return (parseFloat(this.monto) || 0) * (1 - this.nivelPct / 100) },
                    get maxPuntosCanjeables() { return Math.max(0, Math.min(this.puntosDisponibles, Math.floor(this.montoConNivel * 0.5))) },
                    get descuentoPuntos() { return Math.min(parseInt(this.puntosCanjear) || 0, this.maxPuntosCanjeables) },
                    get total() { return Math.max(0, this.montoConNivel - this.descuentoPuntos) + (parseFloat(this.propina) || 0) }
                }"
                class="grid grid-cols-1 lg:grid-cols-3 gap-8"
            >
                <!-- Form Side -->
                <div class="lg:col-span-2 space-y-8">
                    <form method="POST" action="{{ route('payments.store') }}" class="ui-surface space-y-8">
                        @csrf

                        <!-- Appointment Selection -->
                        <div>
                            <label class="ui-label flex items-center gap-2">
                                <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Seleccionar Cita Pendiente
                            </label>
                            <select id="appointment_id" name="appointment_id" class="ui-input !bg-panel border-ink/10 text-ink" required>
                                <option value="">Selecciona el servicio a cobrar...</option>
                                @foreach($appointments as $appointment)
                                    <option value="{{ $appointment->id }}"
                                            data-monto="{{ $appointment->service?->precio }}"
                                            data-nivel-pct="{{ \App\Services\Loyalty\LoyaltyService::discountPct($appointment->client?->nivel ?? 'nuevo') }}"
                                            data-nivel-label="{{ \App\Services\Loyalty\LoyaltyService::LEVEL_LABELS[$appointment->client?->nivel ?? 'nuevo'] }}"
                                            data-puntos="{{ $appointment->client?->puntos ?? 0 }}"
                                            @selected(old('appointment_id') == $appointment->id)>
                                        {{ \Carbon\Carbon::parse($appointment->fecha)->format('d/m') }} - {{ $appointment->client?->user?->name }} ({{ $appointment->service?->nombre }})
                                    </option>
                                @endforeach
                            </select>
                            @error('appointment_id') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                        </div>

                        <!-- Amount Inputs -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="group">
                                <label class="ui-label">Monto del Servicio <span class="text-muted normal-case font-normal">(precio real de la cita, no editable)</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gold font-bold">$</span>
                                    <input type="number" step="0.01" min="0.01" name="monto" x-model="monto" readonly
                                           class="ui-input !pl-10 !bg-panel border-ink/10 text-ink/70 cursor-not-allowed" required>
                                </div>
                                @error('monto') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div class="group">
                                <label class="ui-label">Propina / Gratificación</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-muted font-bold">$</span>
                                    <input type="number" step="0.01" min="0" name="propina" x-model="propina"
                                           class="ui-input !pl-10 !bg-panel border-ink/10 focus:border-gold/50 text-ink">
                                </div>
                                @error('propina') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Loyalty: nivel discount info + points redemption -->
                        <div x-show="nivelLabel" x-cloak class="p-6 rounded-2xl border border-gold/20 bg-ink/3 space-y-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gold flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Cliente <span x-text="nivelLabel"></span>
                                <span x-show="nivelPct > 0" x-text="'· ' + nivelPct + '% de descuento'"></span>
                            </p>

                            <div>
                                <label class="ui-label">Puntos a canjear <span class="text-muted normal-case font-normal" x-text="'(disponibles: ' + puntosDisponibles + ', máximo en este cobro: ' + maxPuntosCanjeables + ')'"></span></label>
                                <input type="number" step="1" min="0" :max="maxPuntosCanjeables" name="puntos_canjeados" x-model="puntosCanjear"
                                       class="ui-input !bg-panel border-ink/10 focus:border-gold/50 text-ink">
                                @error('puntos_canjeados') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                                <p class="mt-2 text-[9px] text-muted leading-relaxed italic">1 punto = $1 MXN. Tope: 50% del total ya con el descuento de nivel aplicado, o el saldo del cliente.</p>
                            </div>
                        </div>

                        <!-- Payment Method Visual Selector -->
                        <div>
                            <label class="ui-label mb-4">Método de Pago</label>
                            <input type="hidden" name="metodo_pago" x-model="metodo">
                            <input type="hidden" name="stripe_payment_id" x-model="stripePaymentId">
                            <div class="grid grid-cols-3 gap-3">
                                @foreach([
                                    'efectivo'      => ['icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'label'=>'Efectivo', 'beta'=>false],
                                    'transferencia' => ['icon'=>'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',                                                            'label'=>'Transfer', 'beta'=>false],
                                    ...(config('services.stripe.key') ? [
                                    'tarjeta'       => ['icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',                    'label'=>'Tarjeta', 'beta'=>true],
                                    ] : []),
                                ] as $id => $opt)
                                    <button type="button"
                                            @click="metodo = '{{ $id }}'"
                                            :class="metodo === '{{ $id }}' ? 'border-gold bg-gold/10 text-gold' : 'border-ink/5 bg-ink/5 text-muted'"
                                            class="relative flex flex-col items-center gap-3 p-4 rounded-2xl border transition-all hover:border-gold/30">
                                        @if($opt['beta'])
                                            <span class="absolute -top-2 -right-2 rounded-full bg-gold px-1.5 py-0.5 text-[7px] font-black uppercase tracking-widest text-black">Beta</span>
                                        @endif
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $opt['icon'] }}" /></svg>
                                        <span class="text-[9px] font-black uppercase tracking-widest">{{ $opt['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if(config('services.stripe.key'))
                        {{-- Stripe card element (visible sólo cuando metodo=tarjeta, aun en beta) --}}
                        <div x-show="metodo === 'tarjeta'" x-cloak class="space-y-4 p-6 rounded-2xl border border-gold/20 bg-ink/3">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gold flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Pago con tarjeta (beta) — procesado por Stripe
                            </p>
                            <div id="stripe-card-element" class="p-4 rounded-xl bg-card border border-ink/10 min-h-[50px]"></div>
                            <div id="stripe-errors" class="text-[10px] font-black text-red-500 uppercase"></div>
                            <button type="button" id="stripe-pay-btn"
                                    class="ui-btn w-full py-3 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                                Cobrar con tarjeta
                            </button>
                        </div>
                        @endif

                        <div x-show="metodo !== 'tarjeta'" class="pt-6">
                            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                                Confirmar y Cerrar Servicio
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Side -->
                <aside class="space-y-6">
                    <div class="ui-card-premium p-8 border-gold/30 gold-glow bg-black/40 backdrop-blur-xl sticky top-24">
                        <h3 class="text-xs font-black text-ink uppercase tracking-[0.2em] mb-8 text-center border-b border-ink/10 pb-4">Resumen de Cobro</h3>
                        
                        <div class="space-y-6">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-muted font-bold uppercase tracking-widest text-[10px]">Subtotal</span>
                                <span class="text-ink font-black" x-text="'$' + (parseFloat(monto) || 0).toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between items-center text-sm" x-show="nivelPct > 0" x-cloak>
                                <span class="text-muted font-bold uppercase tracking-widest text-[10px]" x-text="'Descuento nivel (' + nivelPct + '%)'"></span>
                                <span class="text-green-500 font-black" x-text="'-$' + ((parseFloat(monto) || 0) - montoConNivel).toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between items-center text-sm" x-show="descuentoPuntos > 0" x-cloak>
                                <span class="text-muted font-bold uppercase tracking-widest text-[10px]" x-text="'Puntos canjeados (' + descuentoPuntos + ')'"></span>
                                <span class="text-green-500 font-black" x-text="'-$' + descuentoPuntos.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-muted font-bold uppercase tracking-widest text-[10px]">Propina</span>
                                <span class="text-gold font-black" x-text="'$' + (parseFloat(propina) || 0).toFixed(2)"></span>
                            </div>
                            <div class="ui-divider"></div>
                            <div class="flex justify-between items-center">
                                <span class="text-ink font-black uppercase tracking-widest text-xs">Total a Pagar</span>
                                <span class="text-3xl font-black text-gradient-gold" x-text="'$' + total.toFixed(2)"></span>
                            </div>
                        </div>

                        <div class="mt-10 p-4 rounded-xl bg-ink/5 border border-ink/5">
                            <div class="flex items-center gap-3">
                                <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
                                <p class="text-[10px] font-black text-muted uppercase tracking-widest">Listo para procesar</p>
                            </div>
                            <p class="mt-2 text-[9px] text-muted leading-relaxed italic">Al confirmar, el estado de la cita cambiará automáticamente a 'Completada'.</p>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </div>

    @push('scripts')
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        document.getElementById('appointment_id').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const alpineData = Alpine.$data(document.getElementById('paymentForm'));
            if (selected.value) {
                alpineData.monto = selected.getAttribute('data-monto');
                alpineData.nivelPct = parseFloat(selected.getAttribute('data-nivel-pct')) || 0;
                alpineData.nivelLabel = selected.getAttribute('data-nivel-label') || '';
                alpineData.puntosDisponibles = parseInt(selected.getAttribute('data-puntos')) || 0;
            } else {
                alpineData.nivelPct = 0;
                alpineData.nivelLabel = '';
                alpineData.puntosDisponibles = 0;
            }
            alpineData.puntosCanjear = 0;
        });

        @if(config('services.stripe.key'))
        const stripeKey = '{{ config('services.stripe.key') }}';
        const stripe = Stripe(stripeKey);
        const elements = stripe.elements();
        const cardElement = elements.create('card', {
            style: {
                base: { color: '#fff', fontFamily: 'Figtree, sans-serif', fontSize: '16px', '::placeholder': { color: '#555' } },
                invalid: { color: '#f87171' }
            }
        });
        cardElement.mount('#stripe-card-element');
        cardElement.on('change', ({ error }) => {
            document.getElementById('stripe-errors').textContent = error ? error.message : '';
        });

        document.getElementById('stripe-pay-btn').addEventListener('click', async () => {
            const alpineData = Alpine.$data(document.getElementById('paymentForm'));
            const appointmentId = document.getElementById('appointment_id').value;
            if (!appointmentId) { alert('Selecciona una cita primero.'); return; }

            const res = await fetch('{{ route('api.payments.stripe-intent') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                // El monto no se manda: el servidor lo calcula el mismo (precio base
                // -> descuento de nivel -> puntos canjeados) para que Stripe siempre
                // cobre exactamente lo mismo que luego registrara payments.store().
                body: JSON.stringify({ appointment_id: appointmentId, puntos_canjeados: parseInt(alpineData.puntosCanjear) || 0 })
            });
            const json = await res.json();
            if (!res.ok) { document.getElementById('stripe-errors').textContent = json.message || 'Error al conectar con Stripe.'; return; }

            const { error, paymentIntent } = await stripe.confirmCardPayment(json.data.client_secret, {
                payment_method: { card: cardElement }
            });
            if (error) {
                document.getElementById('stripe-errors').textContent = error.message;
            } else if (paymentIntent.status === 'succeeded') {
                alpineData.stripePaymentId = paymentIntent.id;
                document.querySelector('form').submit();
            }
        });
        @endif
    </script>
    @endpush
</x-app-layout>
