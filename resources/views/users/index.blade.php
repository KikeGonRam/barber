<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title">Usuarios</h2>
            <span class="ui-badge">Administracion</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ui-card px-4 py-2 text-sm">{{ session('status') }}</div>
            @endif

            @if ($errors->has('general'))
                <div class="ui-card px-4 py-2 text-sm">{{ $errors->first('general') }}</div>
            @endif

            <section class="ui-surface">
                <form method="GET" action="{{ route('users.index') }}" class="ui-form-grid mb-4">
                    <div>
                        <label class="ui-label" for="q">Buscar</label>
                        <input id="q" name="q" value="{{ $search }}" placeholder="Nombre o email" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label" for="role">Rol</label>
                        <select id="role" name="role" class="ui-input">
                            <option value="">Todos</option>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" @selected($roleFilter === $role)>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar-group md:col-span-2">
                        <button type="submit" class="ui-btn">Filtrar</button>
                        <a href="{{ route('users.index') }}" class="ui-btn-secondary">Limpiar</a>
                        <a href="{{ route('users.create') }}" class="ui-btn">Nuevo usuario</a>
                    </div>
                </form>

                <div class="ui-list">
                    @forelse($users as $user)
                        <article class="ui-list-item">
                            <div class="ui-list-item-head">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-[#141414]">{{ $user->name }}</h3>
                                    <span class="ui-badge">{{ $user->roles->pluck('name')->join(', ') ?: 'sin rol' }}</span>
                                </div>
                                <div class="ui-toolbar-group">
                                    <a href="{{ route('users.edit', $user) }}" class="ui-btn-secondary">Editar</a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn">Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ui-meta-grid">
                                <div><strong>Email:</strong> {{ $user->email }}</div>
                                <div><strong>Verificado:</strong> {{ $user->email_verified_at ? 'Si' : 'No' }}</div>
                                <div><strong>Creado:</strong> {{ $user->created_at?->format('d/m/Y H:i') }}</div>
                                <div><strong>Ultimo acceso:</strong> {{ $user->updated_at?->format('d/m/Y H:i') }}</div>
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty">No hay usuarios para los filtros seleccionados.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
