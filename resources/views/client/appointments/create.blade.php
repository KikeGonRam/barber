<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Reserva tu <span class="text-gold">Experiencia</span></h2>
                <p class="ui-subtitle">Agenda tu próximo servicio premium en pocos pasos.</p>
            </div>
            <a href="{{ route('client.appointments.index') }}" class="text-xs font-bold uppercase tracking-widest text-muted hover:text-gold transition">
                &larr; Volver a mis citas
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="bookingSystem()">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            @if($preselectedBarber)
            @php
                $preFoto = $preselectedBarber->foto
                    ? (str_starts_with($preselectedBarber->foto, 'http') ? $preselectedBarber->foto : Storage::url($preselectedBarber->foto))
                    : null;
                $preInitials = mb_strtoupper(mb_substr($preselectedBarber->user?->name ?? '?', 0, 2));
            @endphp
            <!-- Pre-selected barber banner -->
            <div class="mb-8 flex items-center gap-4 rounded-2xl border border-gold/20 bg-gold/[0.04] px-5 py-4">
                @if($preFoto)
                    <img src="{{ $preFoto }}" alt="{{ $preselectedBarber->user?->name }}"
                         class="h-12 w-12 rounded-xl object-cover object-top border border-gold/20 flex-shrink-0">
                @else
                    <div class="h-12 w-12 rounded-xl bg-gold/10 border border-gold/20 flex items-center justify-center text-lg font-black text-gold flex-shrink-0">
                        {{ $preInitials }}
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-gold/70">Barbero seleccionado</p>
                    <p class="text-sm font-black text-white uppercase mt-0.5">{{ $preselectedBarber->user?->name }}</p>
                </div>
                <span class="flex items-center gap-1.5 text-[9px] font-black uppercase text-green-400 border border-green-500/20 bg-green-950/30 rounded-lg px-2.5 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span> Listo
                </span>
            </div>
            @endif

            <!-- Progress Bar -->
            <div class="mb-12">
                <div class="flex justify-between mb-2">
                    <template x-for="step in [1,2,3,4,5]">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]"
                              :class="currentStep >= step ? 'text-gold' : 'text-muted'"
                              x-text="'Paso 0' + step"></span>
                    </template>
                </div>
                <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden border border-white/5">
                    <div class="h-full bg-gold transition-all duration-500 shadow-[0_0_15px_rgba(212,175,55,0.5)]"
                         :style="'width: ' + ((currentStep - 1) / 4 * 100) + '%'"></div>
                </div>
            </div>

            <form method="POST" action="{{ route('client.appointments.store') }}" id="booking-form" x-ref="bookingForm">
                @csrf
                <input type="hidden" name="service_id"  :value="selectedService?.id">
                <input type="hidden" name="barber_id"   :value="selectedBarber?.id">
                <input type="hidden" name="fecha"        :value="selectedDate">
                <input type="hidden" name="hora_inicio"  :value="selectedSlot">
                <input type="hidden" name="notas"        :value="notas">
                <input type="hidden" name="metodo_pago"  :value="metodoPago">
                <input type="hidden" name="productos"    :value="JSON.stringify(selectedProducts.map(p => ({product_id: p.id, nombre: p.nombre, precio: p.precio, cantidad: p.cantidad})))">

                <!-- ─────────────────────────────────────────────────
                     PASO 1 — SERVICIO
                ───────────────────────────────────────────────── -->
                <section x-show="currentStep === 1" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">¿Qué servicio deseas hoy?</h3>
                        <p class="text-muted mt-2">Selecciona la experiencia que mejor se adapte a tu estilo.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($services as $service)
                            @php
                                $serviceImg = $service->imagen
                                    ? (str_starts_with($service->imagen, 'http') ? $service->imagen : Storage::url($service->imagen))
                                    : null;
                            @endphp
                            <article
                                @click="selectService({ id: '{{ $service->id }}', name: '{{ addslashes($service->nombre) }}', duration: {{ $service->duracion_min }}, precio: {{ (float) $service->precio }} })"
                                :class="selectedService?.id === '{{ $service->id }}' ? 'border-gold bg-gold/5 gold-glow' : 'border-white/5 bg-white/5'"
                                class="ui-card-premium cursor-pointer transition-all hover:border-gold/30 group overflow-hidden"
                            >
                                @if($serviceImg)
                                    <div class="relative h-36 overflow-hidden">
                                        <img src="{{ $serviceImg }}" alt="{{ $service->nombre }}"
                                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/70"></div>
                                        <span class="absolute bottom-2 left-3 text-[8px] font-black uppercase text-gold/80 border border-gold/20 bg-black/40 px-2 py-0.5 rounded backdrop-blur-sm">{{ $service->categoria }}</span>
                                    </div>
                                @else
                                    @php
                                        $catGrad = match(true) {
                                            str_contains(strtolower($service->categoria ?? ''), 'combo')      => 'from-purple-900/30 to-indigo-900/10',
                                            str_contains(strtolower($service->categoria ?? ''), 'barba')      => 'from-amber-900/30 to-yellow-900/10',
                                            str_contains(strtolower($service->categoria ?? ''), 'trat')       => 'from-emerald-900/30 to-green-900/10',
                                            str_contains(strtolower($service->categoria ?? ''), 'fade')       => 'from-cyan-900/30 to-teal-900/10',
                                            default                                                           => 'from-gold/10 to-gold/[0.02]',
                                        };
                                        $catInitial = mb_strtoupper(mb_substr($service->nombre, 0, 2));
                                    @endphp
                                    <div class="h-36 bg-gradient-to-br {{ $catGrad }} flex items-center justify-center border-b border-white/5 relative overflow-hidden">
                                        <span class="absolute text-[5rem] font-black text-white/[0.04] select-none leading-none">{{ $catInitial }}</span>
                                        <svg class="h-10 w-10 text-gold/20 relative" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-3">
                                        @if(!$serviceImg)
                                            <span class="text-[9px] font-black uppercase text-gold/60 border border-gold/20 px-2 py-0.5 rounded">{{ $service->categoria }}</span>
                                        @else
                                            <span></span>
                                        @endif
                                        <span class="text-xl font-black text-white">${{ number_format($service->precio, 2) }}</span>
                                    </div>
                                    <h4 class="text-lg font-black text-white uppercase group-hover:text-gold transition-colors">{{ $service->nombre }}</h4>
                                    <p class="mt-2 text-xs text-muted font-medium line-clamp-2">{{ $service->descripcion ?: 'Servicio premium de alta precisión.' }}</p>
                                    <div class="mt-4 pt-3 border-t border-white/5 flex items-center gap-2 text-[10px] font-bold text-muted uppercase">
                                        <svg class="h-3.5 w-3.5 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                                        </svg>
                                        {{ $service->duracion_min }} Minutos
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <!-- ─────────────────────────────────────────────────
                     PASO 2 — BARBERO
                ───────────────────────────────────────────────── -->
                <section x-show="currentStep === 2" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">Elige a tu Maestro</h3>
                        <p class="text-muted mt-2">Cada barbero tiene un estilo único. Elige a tu favorito.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach($barbers as $barber)
                            @php
                                $barberFoto = null;
                                if ($barber->foto) {
                                    $barberFoto = str_starts_with($barber->foto, 'http')
                                        ? $barber->foto
                                        : Storage::url($barber->foto);
                                }
                                $barberName = $barber->user?->name ?? '';
                                $initials   = mb_strtoupper(mb_substr($barberName, 0, 2));
                            @endphp
                            <article
                                @click="selectBarber({ id: '{{ $barber->id }}', name: '{{ addslashes($barberName) }}', foto: '{{ $barberFoto }}' })"
                                :class="selectedBarber?.id === '{{ $barber->id }}' ? 'border-gold bg-gold/5 gold-glow' : 'border-white/5 bg-white/5'"
                                class="ui-card-premium cursor-pointer text-center group overflow-hidden transition-all"
                            >
                                @if($barberFoto)
                                    <div class="relative h-40 overflow-hidden">
                                        <img src="{{ $barberFoto }}" alt="{{ $barberName }}"
                                             class="w-full h-full object-cover object-top transition-transform duration-500 group-hover:scale-105">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                                    </div>
                                @else
                                    <div class="relative h-40 bg-gradient-to-b from-gold/[0.08] to-black/40 flex items-center justify-center overflow-hidden">
                                        <span class="absolute text-[6rem] font-black text-white/[0.04] select-none leading-none">{{ $initials }}</span>
                                        <div class="relative h-20 w-20 rounded-2xl bg-black/30 border border-gold/20 flex items-center justify-center text-2xl font-black text-gold group-hover:bg-gold group-hover:text-black group-hover:border-gold transition-all backdrop-blur-sm">
                                            {{ $initials }}
                                        </div>
                                    </div>
                                @endif
                                <div class="p-5">
                                    <h4 class="text-sm font-black text-white uppercase tracking-tight">{{ $barberName }}</h4>
                                    @if($barber->especialidad)
                                        <p class="text-[9px] font-bold text-gold/60 uppercase tracking-widest mt-1">{{ $barber->especialidad }}</p>
                                    @else
                                        <p class="text-[9px] font-bold text-muted uppercase tracking-widest mt-1">Master Groomer</p>
                                    @endif
                                    @if($barber->descripcion)
                                        <p class="mt-2 text-[10px] text-muted/70 line-clamp-2">{{ $barber->descripcion }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="flex justify-center pt-8">
                        <button type="button" @click="currentStep = 1"
                            class="text-xs font-black uppercase text-muted hover:text-white transition">
                            &larr; Volver a Servicios
                        </button>
                    </div>
                </section>

                <!-- ─────────────────────────────────────────────────
                     PASO 3 — FECHA
                ───────────────────────────────────────────────── -->
                <section x-show="currentStep === 3" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">¿Cuándo nos vemos?</h3>
                        <p class="text-muted mt-2">Selecciona una fecha disponible para tu sesión.</p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
                        <template x-for="day in availableDays">
                            <button
                                type="button"
                                @click="selectDate(day.date)"
                                :disabled="day.isSunday"
                                :class="{
                                    'border-gold bg-gold text-black gold-glow': selectedDate === day.date,
                                    'border-white/5 bg-white/5 text-white hover:border-gold/30': selectedDate !== day.date && !day.isSunday,
                                    'border-red-500/10 bg-red-500/5 text-red-400/50 cursor-not-allowed': day.isSunday
                                }"
                                class="flex flex-col items-center p-4 rounded-2xl border transition-all"
                            >
                                <span class="text-[9px] font-black uppercase tracking-widest opacity-60" x-text="day.weekday"></span>
                                <span class="text-xl font-black mt-1" x-text="day.day"></span>
                                <span class="text-[9px] font-bold uppercase mt-1" x-text="day.month"></span>
                            </button>
                        </template>
                    </div>
                    <div class="flex justify-center pt-8">
                        <button type="button" @click="preselectedBarberId ? currentStep = 1 : currentStep = 2"
                            class="text-xs font-black uppercase text-muted hover:text-white transition"
                            x-text="preselectedBarberId ? '← Volver a Servicios' : '← Volver a Barberos'">
                        </button>
                    </div>
                </section>

                <!-- ─────────────────────────────────────────────────
                     PASO 4 — HORARIO
                ───────────────────────────────────────────────── -->
                <section x-show="currentStep === 4" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">Define la hora</h3>
                        <p class="text-muted mt-2" x-text="'Horarios disponibles para el ' + formattedDate"></p>
                    </div>

                    <div x-show="loadingSlots" class="flex justify-center py-12">
                        <div class="h-8 w-8 border-4 border-gold border-t-transparent rounded-full animate-spin"></div>
                    </div>

                    <div x-show="!loadingSlots && slots.length > 0"
                         class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <template x-for="slot in slots">
                            <button
                                type="button"
                                @click="selectedSlot = slot.time"
                                :class="selectedSlot === slot.time
                                    ? 'border-gold bg-gold text-black shadow-[0_0_20px_rgba(212,175,55,0.3)]'
                                    : 'border-white/5 bg-white/[0.03] text-white hover:border-gold/30 hover:bg-white/[0.06]'"
                                class="py-4 px-3 rounded-xl border transition-all text-left group"
                            >
                                <span class="block text-sm font-black" x-text="slot.label"></span>
                                <span class="block text-[9px] font-bold mt-0.5 transition-opacity"
                                      :class="selectedSlot === slot.time ? 'opacity-60' : 'opacity-40 group-hover:opacity-60'"
                                      x-text="'hasta las ' + slot.end_label"></span>
                            </button>
                        </template>
                    </div>

                    <div x-show="!loadingSlots && slots.length === 0" class="text-center py-12 ui-card bg-panel/30 border-dashed">
                        <p class="text-muted italic">No hay horarios disponibles para este día.</p>
                        <p class="text-[10px] text-red-400 uppercase mt-2 font-bold"
                           x-show="new Date(selectedDate + 'T00:00:00').getDay() === 0">
                            Los domingos son días de descanso.
                        </p>
                    </div>

                    <div class="pt-8 flex flex-col items-center gap-6">
                        <button
                            type="button"
                            @click="currentStep = 5"
                            :disabled="!selectedSlot"
                            :class="!selectedSlot ? 'opacity-20 cursor-not-allowed' : 'gold-glow'"
                            class="ui-btn px-16 py-5 text-sm uppercase tracking-[0.2em]"
                        >
                            Continuar &rarr;
                        </button>
                        <button type="button" @click="currentStep = 3"
                            class="text-xs font-black uppercase text-muted hover:text-white transition">
                            &larr; Cambiar Fecha
                        </button>
                    </div>
                </section>

                <!-- ─────────────────────────────────────────────────
                     PASO 5 — EXTRAS & CONFIRMAR
                ───────────────────────────────────────────────── -->
                <section x-show="currentStep === 5" x-transition class="space-y-10">

                    <!-- Summary -->
                    <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                        <p class="text-[9px] font-black uppercase tracking-[0.35em] text-gold mb-4">Resumen de tu reserva</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase">Servicio</p>
                                <p class="text-sm font-black text-white mt-1 uppercase" x-text="selectedService?.name"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase">Barbero</p>
                                <p class="text-sm font-black text-white mt-1" x-text="selectedBarber?.name"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase">Fecha</p>
                                <p class="text-sm font-black text-white mt-1" x-text="formattedDate"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-muted uppercase">Hora</p>
                                <p class="text-sm font-black text-gold mt-1" x-text="selectedSlot"></p>
                            </div>
                        </div>
                    </div>

                    @if($products->isNotEmpty())
                    <!-- Products -->
                    <div class="space-y-5">
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Agrega productos</h3>
                            <p class="text-xs text-muted mt-1">Opcional — añade productos para llevar o usar durante tu sesión.</p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($products as $product)
                                @php
                                    $prodImg = $product->imagen
                                        ? (str_starts_with($product->imagen, 'http') ? $product->imagen : Storage::url($product->imagen))
                                        : null;
                                @endphp
                                <div
                                    :class="isSelected('{{ $product->id }}') ? 'border-gold/40 bg-gold/[0.04]' : 'border-white/5 bg-white/[0.02]'"
                                    class="rounded-2xl border overflow-hidden transition-all"
                                >
                                    @if($prodImg)
                                        <div class="h-28 overflow-hidden">
                                            <img src="{{ $prodImg }}" alt="{{ $product->nombre }}"
                                                 class="w-full h-full object-cover">
                                        </div>
                                    @else
                                        <div class="h-28 bg-white/[0.03] flex items-center justify-center">
                                            <svg class="h-10 w-10 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="p-3">
                                        <p class="text-xs font-black text-white uppercase leading-tight line-clamp-1">{{ $product->nombre }}</p>
                                        <p class="text-[9px] font-bold text-muted uppercase mt-0.5">{{ $product->categoria }}</p>
                                        <p class="text-base font-black text-gold mt-1">${{ number_format($product->precio_venta, 2) }}</p>

                                        <!-- Toggle / Qty -->
                                        <div class="mt-3">
                                            <template x-if="!isSelected('{{ $product->id }}')">
                                                <button type="button"
                                                    @click="addProduct({ id: '{{ $product->id }}', nombre: '{{ addslashes($product->nombre) }}', precio: {{ (float) $product->precio_venta }} })"
                                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 text-[9px] font-black uppercase text-muted hover:border-gold/30 hover:text-gold transition-all">
                                                    + Agregar
                                                </button>
                                            </template>
                                            <template x-if="isSelected('{{ $product->id }}')">
                                                <div class="flex items-center justify-between gap-1">
                                                    <button type="button"
                                                        @click="adjustQty('{{ $product->id }}', -1)"
                                                        class="h-7 w-7 rounded-lg border border-white/10 bg-white/5 text-white font-black text-lg flex items-center justify-center hover:border-red-500/30 hover:text-red-400 transition-all">
                                                        −
                                                    </button>
                                                    <span class="text-sm font-black text-gold" x-text="getQty('{{ $product->id }}')"></span>
                                                    <button type="button"
                                                        @click="adjustQty('{{ $product->id }}', 1)"
                                                        class="h-7 w-7 rounded-lg border border-white/10 bg-white/5 text-white font-black text-lg flex items-center justify-center hover:border-gold/30 hover:text-gold transition-all">
                                                        +
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Notes -->
                    <div class="space-y-3">
                        <label class="text-[9px] font-black uppercase tracking-[0.35em] text-muted">Notas adicionales <span class="text-muted/50">(opcional)</span></label>
                        <textarea
                            x-model="notas"
                            rows="3"
                            placeholder="Ej: tengo alergia a ciertos productos, quiero un look específico..."
                            class="ui-input !bg-panel border-white/10 text-white leading-relaxed w-full"
                        ></textarea>
                    </div>

                    <!-- Total & Submit -->
                    <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 space-y-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted font-bold">Servicio</span>
                            <span class="text-white font-black" x-text="'$' + (selectedService?.precio ?? 0).toFixed(2)"></span>
                        </div>
                        <template x-if="selectedProducts.length > 0">
                            <div>
                                <template x-for="p in selectedProducts" :key="p.id">
                                    <div class="flex justify-between text-sm py-1">
                                        <span class="text-muted" x-text="p.nombre + ' ×' + p.cantidad"></span>
                                        <span class="text-white/70" x-text="'$' + (p.precio * p.cantidad).toFixed(2)"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="pt-3 border-t border-white/10 flex justify-between">
                            <span class="text-[11px] font-black uppercase tracking-widest text-muted">Total estimado</span>
                            <span class="text-xl font-black text-gold" x-text="'$' + grandTotal.toFixed(2)"></span>
                        </div>
                        <p class="text-[9px] text-muted/50">* El precio final puede ajustarse según los productos utilizados durante la sesión.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4 justify-between pt-4">
                        <button type="button" @click="currentStep = 4"
                            class="text-xs font-black uppercase text-muted hover:text-white transition">
                            &larr; Cambiar Horario
                        </button>
                        <button
                            type="button"
                            @click="paymentModal = true"
                            class="ui-btn px-16 py-5 text-sm uppercase tracking-[0.2em] gold-glow"
                        >
                            Confirmar Reserva &rarr;
                        </button>
                    </div>
                </section>
            </form>

            <!-- ─── PAYMENT MODAL ─── -->
            <div
                x-show="paymentModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 sm:p-0"
                @keydown.escape.window="paymentModal = false"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="paymentModal = false"></div>

                <!-- Card -->
                <div
                    x-show="paymentModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative w-full sm:max-w-lg rounded-3xl border border-white/10 bg-[#0d0d0d] shadow-2xl shadow-black/60 overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-white/5">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.3em] text-gold">Último paso</p>
                            <h3 class="text-lg font-black text-white uppercase mt-0.5">¿Cómo deseas pagar?</h3>
                        </div>
                        <button type="button" @click="paymentModal = false"
                            class="h-8 w-8 rounded-xl border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-white hover:border-white/20 transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Mini summary -->
                    <div class="px-6 py-4 flex items-center gap-3 bg-white/[0.02] border-b border-white/5 text-xs">
                        <div class="flex-1 min-w-0">
                            <span class="font-black text-white uppercase" x-text="selectedService?.name"></span>
                            <span class="text-muted"> · </span>
                            <span class="text-muted" x-text="selectedBarber?.name"></span>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <span class="font-black text-gold" x-text="formattedDate"></span>
                            <span class="text-muted/40">·</span>
                            <span class="font-black text-gold" x-text="selectedSlot"></span>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <span class="font-black text-white" x-text="'$' + grandTotal.toFixed(2)"></span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5 space-y-4 max-h-[60vh] overflow-y-auto">

                        <!-- Method buttons -->
                        <div class="grid grid-cols-3 gap-3">

                            <button type="button" @click="metodoPago = 'efectivo'"
                                :class="metodoPago === 'efectivo'
                                    ? 'border-gold bg-gold/[0.08] text-white shadow-[0_0_18px_rgba(212,175,55,0.12)]'
                                    : 'border-white/5 bg-white/[0.02] text-muted hover:border-gold/20'"
                                class="flex flex-col items-center gap-2 py-5 rounded-2xl border transition-all">
                                <svg class="h-6 w-6" :class="metodoPago === 'efectivo' ? 'text-gold' : 'text-muted/40'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <span class="text-[9px] font-black uppercase tracking-wide">Efectivo</span>
                            </button>

                            <button type="button" @click="metodoPago = 'transferencia'"
                                :class="metodoPago === 'transferencia'
                                    ? 'border-gold bg-gold/[0.08] text-white shadow-[0_0_18px_rgba(212,175,55,0.12)]'
                                    : 'border-white/5 bg-white/[0.02] text-muted hover:border-gold/20'"
                                class="flex flex-col items-center gap-2 py-5 rounded-2xl border transition-all">
                                <svg class="h-6 w-6" :class="metodoPago === 'transferencia' ? 'text-gold' : 'text-muted/40'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <span class="text-[9px] font-black uppercase tracking-wide">Transferencia</span>
                            </button>

                            <button type="button" @click="metodoPago = 'tarjeta'"
                                :class="metodoPago === 'tarjeta'
                                    ? 'border-gold bg-gold/[0.08] text-white shadow-[0_0_18px_rgba(212,175,55,0.12)]'
                                    : 'border-white/5 bg-white/[0.02] text-muted hover:border-gold/20'"
                                class="flex flex-col items-center gap-2 py-5 rounded-2xl border transition-all relative">
                                <svg class="h-6 w-6" :class="metodoPago === 'tarjeta' ? 'text-gold' : 'text-muted/40'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <span class="text-[9px] font-black uppercase tracking-wide">Déb / Créd</span>
                                <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-amber-950 border border-amber-500/40 text-[7px] font-black uppercase text-amber-400">beta</span>
                            </button>

                        </div>

                        <!-- Info panels -->
                        @include('client.appointments._payment_panels')

                    </div>

                    <!-- Footer -->
                    <div class="px-6 pb-6 pt-4 border-t border-white/5 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <button type="button" @click="paymentModal = false"
                            class="flex-1 py-3.5 rounded-xl border border-white/10 bg-white/5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-white hover:border-white/20 transition-all">
                            Cancelar
                        </button>
                        <button type="button" @click="$refs.bookingForm.submit()"
                            class="flex-1 ui-btn py-3.5 text-[10px] uppercase tracking-[0.2em] gold-glow">
                            Reservar ahora &rarr;
                        </button>
                    </div>
                </div>
            </div>
            <!-- ─── END PAYMENT MODAL ─── -->

        </div>
    </div>

    @php
        $preselectedBarberJson = $preselectedBarber ? [
            'id'   => (string) $preselectedBarber->id,
            'name' => $preselectedBarber->user?->name,
            'foto' => $preselectedBarber->foto
                ? (str_starts_with($preselectedBarber->foto, 'http')
                    ? $preselectedBarber->foto
                    : \Illuminate\Support\Facades\Storage::url($preselectedBarber->foto))
                : null,
        ] : null;
    @endphp
    <script>
        function bookingSystem() {
            return {
                currentStep: 1,
                selectedService: null,
                selectedBarber: null,
                selectedDate: null,
                selectedSlot: null,
                selectedProducts: [],
                notas: '',
                metodoPago: 'efectivo',
                paymentModal: false,
                slots: [],
                loadingSlots: false,
                availableDays: [],
                preselectedBarberId: @json($preselectedBarber ? (string)$preselectedBarber->id : null),
                preselectedBarber: @json($preselectedBarberJson),

                init() {
                    this.generateDays();
                    if (this.preselectedBarberId && this.preselectedBarber) {
                        this.selectedBarber = this.preselectedBarber;
                    }
                },

                generateDays() {
                    const days     = [];
                    const months   = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                    const weekdays = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
                    for (let i = 0; i < 30; i++) {
                        const d = new Date();
                        d.setDate(d.getDate() + i);
                        days.push({
                            date:    d.toISOString().split('T')[0],
                            day:     d.getDate(),
                            month:   months[d.getMonth()],
                            weekday: weekdays[d.getDay()],
                            isSunday: d.getDay() === 0,
                        });
                    }
                    this.availableDays = days;
                },

                selectService(service) {
                    this.selectedService = service;
                    this.currentStep = this.preselectedBarberId ? 3 : 2;
                },

                selectBarber(barber) {
                    this.selectedBarber = barber;
                    this.currentStep = 3;
                },

                async selectDate(date) {
                    this.selectedDate = date;
                    this.selectedSlot = null;
                    this.currentStep  = 4;
                    await this.fetchSlots();
                },

                async fetchSlots() {
                    this.loadingSlots = true;
                    try {
                        const url = `/api/v1/availability/slots?barber_id=${this.selectedBarber.id}&service_id=${this.selectedService.id}&date=${this.selectedDate}`;
                        const res = await fetch(url);
                        if (!res.ok) throw new Error(`HTTP ${res.status}`);
                        const data = await res.json();
                        this.slots = data.slots;
                    } catch (e) {
                        this.slots = [];
                        console.error('Error fetching slots', e);
                    } finally {
                        this.loadingSlots = false;
                    }
                },

                // Products
                addProduct(product) {
                    if (!this.isSelected(product.id)) {
                        this.selectedProducts.push({ ...product, cantidad: 1 });
                    }
                },

                adjustQty(productId, delta) {
                    const idx = this.selectedProducts.findIndex(p => p.id === productId);
                    if (idx < 0) return;
                    const newQty = this.selectedProducts[idx].cantidad + delta;
                    if (newQty <= 0) {
                        this.selectedProducts.splice(idx, 1);
                    } else {
                        this.selectedProducts[idx].cantidad = newQty;
                    }
                },

                isSelected(productId) {
                    return this.selectedProducts.some(p => p.id === productId);
                },

                getQty(productId) {
                    return this.selectedProducts.find(p => p.id === productId)?.cantidad ?? 0;
                },

                get extrasTotal() {
                    return this.selectedProducts.reduce((sum, p) => sum + p.precio * p.cantidad, 0);
                },

                get grandTotal() {
                    return (this.selectedService?.precio ?? 0) + this.extrasTotal;
                },

                get formattedDate() {
                    if (!this.selectedDate) return '';
                    const [y, m, d] = this.selectedDate.split('-');
                    return `${d}/${m}/${y}`;
                },
            };
        }
    </script>
</x-app-layout>
