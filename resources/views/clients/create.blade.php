<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Registro de <span class="text-gold">Nuevo Cliente</span></h2>
                <p class="ui-subtitle">Crea un perfil de cliente para agendar citas y fidelizar.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-ink transition">
                &larr; Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('clients.store') }}" class="space-y-10">
                    @csrf

                    <!-- Section: Personal Info -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Información Personal</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="name">Nombre Completo</label>
                                <input id="name" name="name" value="{{ old('name') }}" class="ui-input !bg-panel border-ink/10 text-ink" required placeholder="Ej: Roberto Gómez">
                                @error('name') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="email">Correo Electrónico</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" class="ui-input !bg-panel border-ink/10 text-ink" required placeholder="cliente@email.com">
                                @error('email') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="telefono">Teléfono / WhatsApp</label>
                                <input id="telefono" name="telefono" value="{{ old('telefono') }}" class="ui-input !bg-panel border-ink/10 text-ink" placeholder="+52 ...">
                                @error('telefono') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="fecha_nacimiento">Fecha de Nacimiento</label>
                                <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}" class="ui-input !bg-panel border-ink/10 text-ink">
                                @error('fecha_nacimiento') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Credentials -->
                    <div class="pt-8 border-t border-ink/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Acceso al Portal (Opcional)</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="password">Contraseña</label>
                                <input id="password" type="password" name="password" class="ui-input !bg-panel border-ink/10 text-ink" placeholder="••••••••">
                                <p class="mt-2 text-[9px] text-muted uppercase font-bold italic">Dejar vacío para auto-generar.</p>
                            </div>
                            <div>
                                <label class="ui-label" for="password_confirmation">Confirmar Contraseña</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" class="ui-input !bg-panel border-ink/10 text-ink" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Notifications -->
                    <div class="pt-8 border-t border-ink/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Preferencias de Contacto</h3>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach(['pref_in_app' => 'Sistema', 'pref_email' => 'Email', 'pref_sms' => 'SMS', 'pref_whatsapp' => 'WhatsApp'] as $name => $label)
                                <label class="relative flex cursor-pointer items-center justify-center rounded-xl border border-ink/5 bg-ink/5 p-4 transition-all hover:border-gold/30 has-[:checked]:border-gold has-[:checked]:bg-gold/10 group">
                                    <input type="checkbox" name="{{ $name }}" value="1" class="sr-only" @checked(old($name, in_array($name, ['pref_in_app', 'pref_email'])) )>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-muted group-hover:text-gold transition-colors">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-10 border-t border-ink/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Registrar Cliente Maestro <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
