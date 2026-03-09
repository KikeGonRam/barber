<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Refinar <span class="text-gold">Perfil de Cliente</span></h2>
                <p class="ui-subtitle">Actualiza la información de contacto y preferencias de este miembro.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-10">
                    @csrf
                    @method('PUT')

                    <!-- Section: Personal Info -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Información de Perfil</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="name">Nombre Completo</label>
                                <input id="name" name="name" value="{{ old('name', $client->user?->name) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('name') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="email">Correo Electrónico</label>
                                <input id="email" type="email" name="email" value="{{ old('email', $client->user?->email) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('email') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="telefono">Teléfono / WhatsApp</label>
                                <input id="telefono" name="telefono" value="{{ old('telefono', $client->telefono) }}" class="ui-input !bg-panel border-white/10 text-white">
                                @error('telefono') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="fecha_nacimiento">Fecha de Nacimiento</label>
                                <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $client->fecha_nacimiento?->format('Y-m-d')) }}" class="ui-input !bg-panel border-white/10 text-white">
                                @error('fecha_nacimiento') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Notifications -->
                    <div class="pt-8 border-t border-white/5">
                        @php
                            $prefs = old('pref_in_app') !== null
                                ? [
                                    'in_app' => old('pref_in_app'),
                                    'email' => old('pref_email'),
                                    'sms' => old('pref_sms'),
                                    'whatsapp' => old('pref_whatsapp'),
                                ]
                                : ($client->preferencias_notificacion ?? []);
                        @endphp
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Canales de Notificación</h3>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach(['pref_in_app' => 'Sistema', 'pref_email' => 'Email', 'pref_sms' => 'SMS', 'pref_whatsapp' => 'WhatsApp'] as $name => $label)
                                @php $key = str_replace('pref_', '', $name); @endphp
                                <label class="relative flex cursor-pointer items-center justify-center rounded-xl border border-white/5 bg-white/5 p-4 transition-all hover:border-gold/30 has-[:checked]:border-gold has-[:checked]:bg-gold/10 group">
                                    <input type="checkbox" name="{{ $name }}" value="1" class="sr-only" @checked((bool) ($prefs[$key] ?? false))>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-muted group-hover:text-gold transition-colors">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Actualizar Ficha de Cliente <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
