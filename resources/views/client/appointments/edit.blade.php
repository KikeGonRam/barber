<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Reagendar <span class="text-gold">Cita</span></h2>
                <p class="ui-subtitle">Ajusta el horario de tu sesión premium.</p>
            </div>
            <a href="{{ route('client.appointments.index') }}" class="text-xs font-bold uppercase tracking-widest text-muted hover:text-gold transition">
                &larr; Volver a mis citas
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="bookingSystem()">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('client.appointments.update', $appointment) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="service_id" :value="selectedService?.id">
                <input type="hidden" name="barber_id" :value="selectedBarber?.id">
                <input type="hidden" name="fecha" :value="selectedDate">
                <input type="hidden" name="hora_inicio" :value="selectedSlot">

                <div class="ui-surface space-y-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Summary -->
                        <div class="space-y-6">
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest border-b border-ink/5 pb-4">Detalles Actuales</h3>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-[10px] font-bold text-muted uppercase">Servicio</p>
                                    <p class="text-lg font-black text-ink uppercase">{{ $appointment->service->nombre }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-muted uppercase">Barbero</p>
                                    <p class="text-lg font-black text-gold uppercase">{{ $appointment->barber->user->name }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Date Selection -->
                        <div class="space-y-6">
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest border-b border-ink/5 pb-4">Nueva Fecha</h3>
                            <input type="date" name="fecha_picker" x-model="selectedDate" @change="fetchSlots()" class="ui-input !bg-panel border-ink/10 text-ink" min="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <!-- Time Slots -->
                    <div class="space-y-6">
                        <h3 class="text-sm font-black text-ink uppercase tracking-widest border-b border-ink/5 pb-4">Horarios Disponibles</h3>
                        
                        <div x-show="loadingSlots" class="flex justify-center py-8">
                            <div class="h-6 w-6 border-2 border-gold border-t-transparent rounded-full animate-spin"></div>
                        </div>

                        <div x-show="!loadingSlots && slots.length > 0" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                            <template x-for="slot in slots">
                                <button 
                                    type="button"
                                    @click="selectedSlot = slot.time"
                                    :class="selectedSlot === slot.time ? 'border-gold bg-gold text-black' : 'border-ink/5 bg-ink/5 text-ink hover:border-gold/30'"
                                    class="py-2.5 rounded-xl border font-black text-[10px] transition-all"
                                    x-text="slot.label"
                                ></button>
                            </template>
                        </div>

                        <div x-show="!loadingSlots && slots.length === 0" class="text-center py-8 bg-ink/5 rounded-2xl border border-dashed border-ink/10">
                            <p class="text-xs text-muted">No hay horarios para este día.</p>
                        </div>
                    </div>

                    <div class="pt-8 border-t border-ink/5 flex justify-end">
                        <button type="submit" :disabled="!selectedSlot" :class="!selectedSlot ? 'opacity-20 cursor-not-allowed' : 'gold-glow'" class="ui-btn px-16 py-4">
                            Confirmar Reprogramación
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function bookingSystem() {
            return {
                selectedService: { id: @json((string) $appointment->service_id) },
                selectedBarber: { id: @json((string) $appointment->barber_id) },
                selectedDate: '{{ $appointment->fecha->format('Y-m-d') }}',
                selectedSlot: '{{ substr($appointment->hora_inicio, 0, 5) }}',
                currentDate: '{{ $appointment->fecha->format('Y-m-d') }}',
                currentSlot: {
                    time: '{{ substr($appointment->hora_inicio, 0, 5) }}',
                    label: '{{ \Carbon\Carbon::parse($appointment->hora_inicio)->format('g:i A') }}',
                },
                slots: [],
                loadingSlots: false,

                init() {
                    this.fetchSlots();
                },

                async fetchSlots() {
                    if (!this.selectedDate) return;
                    this.loadingSlots = true;
                    try {
                        const response = await fetch(`/api/v1/availability/slots?barber_id=${this.selectedBarber.id}&service_id=${this.selectedService.id}&date=${this.selectedDate}`);
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        const data = await response.json();
                        this.slots = data.slots;
                        if (this.selectedDate === this.currentDate && !this.slots.some(slot => slot.time === this.currentSlot.time)) {
                            this.slots.unshift({ ...this.currentSlot, current: true });
                        }
                    } catch (e) {
                        this.slots = [];
                        console.error('Error fetching slots', e);
                    } finally {
                        this.loadingSlots = false;
                    }
                }
            }
        }
    </script>
</x-app-layout>
