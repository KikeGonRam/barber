<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Editar usuario</h2>
            <span class="ui-badge">Administracion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="name">Nombre</label>
                            <input id="name" name="name" value="{{ old('name', $user->name) }}" class="ui-input" required>
                            @error('name') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="ui-input" required>
                            @error('email') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="role">Rol</label>
                            <select id="role" name="role" class="ui-input" required>
                                <option value="">Seleccionar rol</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" @selected(old('role', $user->roles->first()?->name) === $role)>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="ui-divider my-2"></div>
                    <p class="text-sm font-semibold text-[#2f2f2f]">Cambiar contrasena (opcional)</p>

                    <div class="ui-form-grid">
                        <div>
                            <label class="ui-label" for="password">Nueva contrasena</label>
                            <input id="password" type="password" name="password" class="ui-input">
                            @error('password') <p class="text-sm text-[#525252]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="password_confirmation">Confirmar contrasena</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="ui-input">
                        </div>
                    </div>

                    <div class="ui-toolbar-group pt-2">
                        <button type="submit" class="ui-btn">Actualizar usuario</button>
                        <a href="{{ route('users.index') }}" class="ui-btn-secondary">Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
