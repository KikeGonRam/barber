<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Nuevo cliente</h2>
            <span class="ui-badge">Atencion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('clients.store') }}" class="space-y-4">
                    @csrf

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="name">Nombre</label>
                            <input id="name" name="name" value="{{ old('name') }}" class="ui-input" required>
                            @error('name') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="ui-input" required>
                            @error('email') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="password">Contrasena (opcional)</label>
                            <input id="password" type="password" name="password" class="ui-input">
                            <p class="text-xs text-[#666] mt-1">Si lo dejas vacio, el sistema genera una automaticamente.</p>
                            @error('password') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="password_confirmation">Confirmar contrasena</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="ui-input">
                        </div>
                        <div>
                            <label class="ui-label" for="telefono">Telefono</label>
                            <input id="telefono" name="telefono" value="{{ old('telefono') }}" class="ui-input">
                            @error('telefono') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="fecha_nacimiento">Fecha nacimiento</label>
                            <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}" class="ui-input">
                            @error('fecha_nacimiento') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <p class="ui-label mb-2">Preferencias de notificacion</p>
                        <div class="ui-form-grid">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_in_app" value="1" class="rounded border-[#bfbfbf]" @checked(old('pref_in_app', true))>
                                In-app
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_email" value="1" class="rounded border-[#bfbfbf]" @checked(old('pref_email', true))>
                                Email
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_sms" value="1" class="rounded border-[#bfbfbf]" @checked(old('pref_sms', false))>
                                SMS
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_whatsapp" value="1" class="rounded border-[#bfbfbf]" @checked(old('pref_whatsapp', false))>
                                WhatsApp
                            </label>
                        </div>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar cliente</button>
                        <a href="{{ route('clients.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
