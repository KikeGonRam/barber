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
            
            <!-- Progress Bar -->
            <div class="mb-12">
                <div class="flex justify-between mb-2">
                    <template x-for="step in [1,2,3,4]">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]" :class="currentStep >= step ? 'text-gold' : 'text-muted'" x-text="'Paso 0' + step"></span>
                    </template>
                </div>
                <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden border border-white/5">
                    <div class="h-full bg-gold transition-all duration-500 shadow-[0_0_15px_rgba(212,175,55,0.5)]" :style="'width: ' + ((currentStep - 1) / 3 * 100) + '%'"></div>
                </div>
            </div>

            <form method="POST" action="{{ route('client.appointments.store') }}" id="booking-form">
                @csrf
                <input type="hidden" name="service_id" :value="selectedService?.id">
                <input type="hidden" name="barber_id" :value="selectedBarber?.id">
                <input type="hidden" name="fecha" :value="selectedDate">
                <input type="hidden" name="hora_inicio" :value="selectedSlot">

                <!-- STEP 1: SERVICE -->
                <section x-show="currentStep === 1" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">¿Qué servicio deseas hoy?</h3>
                        <p class="text-muted mt-2">Selecciona la experiencia que mejor se adapte a tu estilo.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($services as $service)
                            <article 
                                @click="selectService({ id: {{ $service->id }}, name: '{{ $service->nombre }}', duration: {{ $service->duracion_min }} })"
                                :class="selectedService?.id === {{ $service->id }} ? 'border-gold bg-gold/5 gold-glow' : 'border-white/5 bg-white/5'"
                                class="ui-card-premium p-8 cursor-pointer transition-all hover:border-gold/30 group"
                            >
                                <div class="flex justify-between items-start mb-6">
                                    <span class="text-[9px] font-black uppercase text-gold/60 border border-gold/20 px-2 py-0.5 rounded">{{ $service->categoria }}</span>
                                    <span class="text-xl font-black text-white">${{ number_format($service->precio, 2) }}</span>
                                </div>
                                <h4 class="text-xl font-black text-white uppercase group-hover:text-gold transition-colors">{{ $service->nombre }}</h4>
                                <p class="mt-4 text-xs text-muted font-medium">{{ $service->descripcion ?: 'Servicio premium de alta precisión.' }}</p>
                                <div class="mt-6 pt-4 border-t border-white/5 flex items-center gap-2 text-[10px] font-bold text-muted uppercase">
                                    <svg class="h-3.5 w-3.5 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                                    {{ $service->duracion_min }} Minutos
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <!-- STEP 2: BARBER -->
                <section x-show="currentStep === 2" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">Elige a tu Maestro</h3>
                        <p class="text-muted mt-2">Cada barbero tiene un estilo único. Elige a tu favorito.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                        @foreach($barbers as $barber)
                            <article 
                                @click="selectBarber({ id: {{ $barber->id }}, name: '{{ $barber->user?->name }}' })"
                                :class="selectedBarber?.id === {{ $barber->id }} ? 'border-gold bg-gold/5 gold-glow' : 'border-white/5 bg-white/5'"
                                class="ui-card-premium p-6 cursor-pointer text-center group"
                            >
                                <div class="h-20 w-20 rounded-2xl bg-bg-accent border border-white/10 mx-auto mb-4 flex items-center justify-center text-xl font-black text-gold group-hover:bg-gold group-hover:text-black transition-all">
                                    {{ substr($barber->user?->name, 0, 2) }}
                                </div>
                                <h4 class="text-sm font-black text-white uppercase tracking-tight">{{ $barber->user?->name }}</h4>
                                <p class="text-[9px] font-bold text-muted uppercase tracking-widest mt-1">Master Groomer</p>
                            </article>
                        @endforeach
                    </div>
                    <div class="flex justify-center pt-8">
                        <button type="button" @click="currentStep = 1" class="text-xs font-black uppercase text-muted hover:text-white transition">&larr; Volver a Servicios</button>
                    </div>
                </section>

                <!-- STEP 3: DATE -->
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
                                :class="{
                                    'border-gold bg-gold text-black gold-glow': selectedDate === day.date,
                                    'border-white/5 bg-white/5 text-white hover:border-gold/30': selectedDate !== day.date && !day.isSunday,
                                    'border-red-500/10 bg-red-500/5 text-red-400 opacity-50': day.isSunday && selectedDate !== day.date
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
                        <button type="button" @click="currentStep = 2" class="text-xs font-black uppercase text-muted hover:text-white transition">&larr; Volver a Barberos</button>
                    </div>
                </section>

                <!-- STEP 4: TIME SLOT -->
                <section x-show="currentStep === 4" x-transition class="space-y-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter">Define la hora</h3>
                        <p class="text-muted mt-2" x-text="'Horarios disponibles para el ' + formattedDate"></p>
                    </div>

                    <div x-show="loadingSlots" class="flex justify-center py-12">
                        <div class="h-8 w-8 border-4 border-gold border-t-transparent rounded-full animate-spin"></div>
                    </div>

                    <div x-show="!loadingSlots && slots.length > 0" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                        <template x-for="slot in slots">
                            <button 
                                type="button"
                                @click="selectedSlot = slot.time"
                                :class="selectedSlot === slot.time ? 'border-gold bg-gold text-black' : 'border-white/5 bg-white/5 text-white hover:border-gold/30'"
                                class="py-3 rounded-xl border font-black text-xs transition-all"
                                x-text="slot.label"
                            ></button>
                        </template>
                    </div>

                    <div x-show="!loadingSlots && slots.length === 0" class="text-center py-12 ui-card bg-panel/30 border-dashed">
                        <p class="text-muted italic">No hay horarios disponibles para este día.</p>
                        <p class="text-[10px] text-gold/50 uppercase mt-2 font-bold" x-show="selectedDate === new Date().toISOString().split('T')[0]">Nota: Puede que la jornada laboral ya haya terminado por hoy.</p>
                        <p class="text-[10px] text-red-400 uppercase mt-2 font-bold" x-show="new Date(selectedDate + 'T00:00:00').getDay() === 0">Nota: Los domingos son días de descanso para nuestros maestros.</p>
                    </div>

                    <div class="pt-12 flex flex-col items-center gap-8">
                        <div x-show="selectedSlot" class="text-center animate-bounce">
                            <p class="text-[10px] font-black uppercase text-gold tracking-[0.3em] mb-2">Resumen de Reserva</p>
                            <p class="text-lg font-black text-white uppercase" x-text="selectedService?.name + ' con ' + selectedBarber?.name"></p>
                            <p class="text-sm text-muted font-bold" x-text="formattedDate + ' a las ' + selectedSlot"></p>
                        </div>

                        <div class="flex items-center gap-6">
                            <button type="button" @click="currentStep = 3" class="text-xs font-black uppercase text-muted hover:text-white transition">&larr; Cambiar Fecha</button>
                            <button 
                                type="submit" 
                                :disabled="!selectedSlot"
                                :class="!selectedSlot ? 'opacity-20 cursor-not-allowed' : 'gold-glow'"
                                class="ui-btn px-16 py-5 text-sm uppercase tracking-[0.2em]"
                            >
                                Confirmar Reserva &rarr;
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </div>

    <script>
        function bookingSystem() {
            return {
                currentStep: 1,
                selectedService: null,
                selectedBarber: null,
                selectedDate: null,
                selectedSlot: null,
                slots: [],
                loadingSlots: false,
                availableDays: [],

                init() {
                    this.generateDays();
                },

                generateDays() {
                    const days = [];
                    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                    const weekdays = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                    
                    for (let i = 0; i < 30; i++) {
                        const date = new Date();
                        date.setDate(date.getDate() + i);
                        
                        days.push({
                            date: date.toISOString().split('T')[0],
                            day: date.getDate(),
                            month: months[date.getMonth()],
                            weekday: weekdays[date.getDay()],
                            isSunday: date.getDay() === 0
                        });
                    }
                    this.availableDays = days;
                },

                selectService(service) {
                    this.selectedService = service;
                    this.currentStep = 2;
                },

                selectBarber(barber) {
                    this.selectedBarber = barber;
                    this.currentStep = 3;
                },

                async selectDate(date) {
                    this.selectedDate = date;
                    this.selectedSlot = null;
                    this.currentStep = 4;
                    await this.fetchSlots();
                },

                async fetchSlots() {
                    this.loadingSlots = true;
                    try {
                        const response = await fetch(`/api/availability/slots?barber_id=${this.selectedBarber.id}&service_id=${this.selectedService.id}&date=${this.selectedDate}`);
                        const data = await response.json();
                        this.slots = data.slots;
                    } catch (e) {
                        console.error('Error fetching slots', e);
                    } finally {
                        this.loadingSlots = false;
                    }
                },

                get formattedDate() {
                    if (!this.selectedDate) return '';
                    const [year, month, day] = this.selectedDate.split('-');
                    return `${day}/${month}/${year}`;
                }
            }
        }
    </script>
</x-app-layout>
