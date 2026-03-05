<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Crear usuario</h2>
            <span class="ui-badge">Administracion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
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
                            <label class="ui-label" for="role">Rol</label>
                            <select id="role" name="role" class="ui-input" required>
                                <option value="">Seleccionar rol</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="password">Contrasena</label>
                            <input id="password" type="password" name="password" class="ui-input" required>
                            @error('password') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="password_confirmation">Confirmar contrasena</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="ui-input" required>
                        </div>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Guardar usuario</button>
                        <a href="{{ route('users.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
