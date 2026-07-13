<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="ui-title">Preferencias de <span class="text-gold">notificación</span></h2>
            <p class="ui-subtitle">Elige cómo y para qué quieres que te contactemos.</p>
        </div>
    </x-slot>

    <div class="py-4 max-w-2xl">
        @if(session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="ui-card-premium p-6 sm:p-8 space-y-2">
            @csrf
            @method('PATCH')

            @php
                $channels = [
                    ['key' => 'in_app',      'label' => 'Notificaciones en la app', 'desc' => 'Avisos dentro de tu panel.'],
                    ['key' => 'email',       'label' => 'Correo electrónico',        'desc' => 'Confirmaciones, recibos y recordatorios por email.'],
                    ['key' => 'sms',         'label' => 'SMS',                        'desc' => 'Mensajes de texto. Requiere teléfono registrado.'],
                    ['key' => 'whatsapp',    'label' => 'WhatsApp',                   'desc' => 'Avisos por WhatsApp. Requiere teléfono registrado.'],
                ];
            @endphp

            <p class="text-[10px] font-black uppercase tracking-widest text-muted mb-2">Canales</p>
            @foreach($channels as $ch)
                <label for="pref_{{ $ch['key'] }}" class="flex items-center justify-between gap-4 py-3.5 border-b border-white/5 cursor-pointer group">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-white">{{ $ch['label'] }}</p>
                        <p class="text-[11px] text-muted mt-0.5">{{ $ch['desc'] }}</p>
                    </div>
                    <div class="relative shrink-0">
                        <input type="checkbox" id="pref_{{ $ch['key'] }}" name="{{ $ch['key'] }}" value="1"
                               class="peer sr-only" @checked($prefs[$ch['key']] ?? false)>
                        <div class="h-6 w-11 rounded-full bg-white/10 border border-white/10 peer-checked:bg-gold/80 peer-checked:border-gold transition-all"></div>
                        <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-all peer-checked:translate-x-5"></div>
                    </div>
                </label>
            @endforeach

            <p class="text-[10px] font-black uppercase tracking-widest text-muted pt-5 pb-2">Marketing</p>
            <label for="pref_promociones" class="flex items-center justify-between gap-4 py-3.5 cursor-pointer group">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white">Promociones y ofertas</p>
                    <p class="text-[11px] text-muted mt-0.5">Novedades, descuentos y campañas. Puedes darte de baja cuando quieras.</p>
                </div>
                <div class="relative shrink-0">
                    <input type="checkbox" id="pref_promociones" name="promociones" value="1"
                           class="peer sr-only" @checked($prefs['promociones'] ?? false)>
                    <div class="h-6 w-11 rounded-full bg-white/10 border border-white/10 peer-checked:bg-gold/80 peer-checked:border-gold transition-all"></div>
                    <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-all peer-checked:translate-x-5"></div>
                </div>
            </label>

            <div class="pt-6 flex justify-end">
                <button type="submit" class="ui-btn px-8 py-3 text-[11px] tracking-widest">Guardar preferencias</button>
            </div>
        </form>
    </div>
</x-app-layout>
