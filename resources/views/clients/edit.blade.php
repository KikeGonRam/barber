<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Editar cliente</h2>
            <span class="ui-badge">Atencion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="name">Nombre</label>
                            <input id="name" name="name" value="{{ old('name', $client->user?->name) }}" class="ui-input" required>
                            @error('name') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $client->user?->email) }}" class="ui-input" required>
                            @error('email') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="telefono">Telefono</label>
                            <input id="telefono" name="telefono" value="{{ old('telefono', $client->telefono) }}" class="ui-input">
                            @error('telefono') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="fecha_nacimiento">Fecha nacimiento</label>
                            <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $client->fecha_nacimiento?->format('Y-m-d')) }}" class="ui-input">
                            @error('fecha_nacimiento') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

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

                    <div>
                        <p class="ui-label mb-2">Preferencias de notificacion</p>
                        <div class="ui-form-grid">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_in_app" value="1" class="rounded border-[#bfbfbf]" @checked((bool) ($prefs['in_app'] ?? true))>
                                In-app
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_email" value="1" class="rounded border-[#bfbfbf]" @checked((bool) ($prefs['email'] ?? true))>
                                Email
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_sms" value="1" class="rounded border-[#bfbfbf]" @checked((bool) ($prefs['sms'] ?? false))>
                                SMS
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="pref_whatsapp" value="1" class="rounded border-[#bfbfbf]" @checked((bool) ($prefs['whatsapp'] ?? false))>
                                WhatsApp
                            </label>
                        </div>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar cliente</button>
                        <a href="{{ route('clients.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
