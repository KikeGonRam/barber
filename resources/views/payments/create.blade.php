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
                    get total() { return (parseFloat(this.monto) || 0) + (parseFloat(this.propina) || 0) }
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
                                <label class="ui-label">Monto del Servicio</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gold font-bold">$</span>
                                    <input type="number" step="0.01" min="0.01" name="monto" x-model="monto"
                                           class="ui-input !pl-10 !bg-panel border-ink/10 focus:border-gold/50 text-ink" required>
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

                        <!-- Payment Method Visual Selector -->
                        <div>
                            <label class="ui-label mb-4">Método de Pago</label>
                            <input type="hidden" name="metodo_pago" x-model="metodo">
                            <input type="hidden" name="stripe_payment_id" x-model="stripePaymentId">
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                @foreach([
                                    'efectivo'      => ['icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'label'=>'Efectivo'],
                                    'tarjeta'       => ['icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',                    'label'=>'Tarjeta'],
                                    'transferencia' => ['icon'=>'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',                                                            'label'=>'Transfer'],
                                    'qr'            => ['icon'=>'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z', 'label'=>'QR'],
                                    'stripe'        => ['icon'=>'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',           'label'=>'Stripe'],
                                ] as $id => $opt)
                                    <button type="button"
                                            @click="metodo = '{{ $id }}'"
                                            :class="metodo === '{{ $id }}' ? 'border-gold bg-gold/10 text-gold' : 'border-ink/5 bg-ink/5 text-muted'"
                                            class="flex flex-col items-center gap-3 p-4 rounded-2xl border transition-all hover:border-gold/30">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $opt['icon'] }}" /></svg>
                                        <span class="text-[9px] font-black uppercase tracking-widest">{{ $opt['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Stripe card element (visible sólo cuando metodo=stripe) --}}
                        <div x-show="metodo === 'stripe'" x-cloak class="space-y-4 p-6 rounded-2xl border border-gold/20 bg-ink/3">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gold flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Pago seguro con Stripe
                            </p>
                            <div id="stripe-card-element" class="p-4 rounded-xl bg-card border border-ink/10 min-h-[50px]"></div>
                            <div id="stripe-errors" class="text-[10px] font-black text-red-500 uppercase"></div>
                            <button type="button" id="stripe-pay-btn"
                                    class="ui-btn w-full py-3 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                                Pagar con Stripe
                            </button>
                        </div>

                        <div x-show="metodo !== 'stripe'" class="pt-6">
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
            if (selected.value) {
                const monto = selected.getAttribute('data-monto');
                const alpineData = Alpine.$data(document.getElementById('paymentForm'));
                alpineData.monto = monto;
            }
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
                body: JSON.stringify({ monto: alpineData.monto, appointment_id: appointmentId })
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
