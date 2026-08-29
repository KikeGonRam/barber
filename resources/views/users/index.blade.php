@php
    $activeFilters = array_filter(['q' => request('q'), 'role' => request('role'), 'verified' => request('verified')], fn($v) => $v !== '' && $v !== null);
    $roleBadgeClasses = [
        'administrador'  => 'border-red-500/25 bg-red-500/10 text-red-400',
        'barbero'        => 'border-gold/25 bg-gold/10 text-gold',
        'recepcionista'  => 'border-blue-500/25 bg-blue-500/10 text-blue-400',
        'cliente'        => 'border-cyan-500/25 bg-cyan-500/10 text-cyan-400',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestión de <span class="text-gold">Usuarios</span></h2>
                <p class="ui-subtitle">Accesos, roles y verificaciones del sistema.</p>
            </div>
            <a href="{{ route('users.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo Usuario
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

        @if($errors->any())
            <div class="rounded-2xl border border-red-500/25 bg-red-500/8 p-4">
                <ul class="space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="text-xs font-bold text-red-400">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── FILTROS ─────────────────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-ink/6" @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-ink uppercase tracking-widest">Filtros</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('users.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ request('q') }}" class="ui-input pl-10" placeholder="Nombre o email..." aria-label="Buscar usuarios por nombre o email">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Rol</label>
                            <select name="role" class="ui-input mt-1">
                                <option value="">Todos los roles</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" @selected(request('role') == $role)>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Verificación</label>
                            <select name="verified" class="ui-input mt-1">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('verified') === '1')>Verificados</option>
                                <option value="0" @selected(request('verified') === '0')>Sin verificar</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('users.index') }}" class="flex items-center gap-1.5 rounded-xl border border-ink/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-ink transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @if(count($activeFilters) > 0)
                <div class="flex flex-wrap gap-2 px-6 pb-4">
                    @foreach($activeFilters as $key => $val)
                        @php $labels = ['q'=>'Búsqueda','role'=>'Rol','verified'=>'Verificación']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}: {{ $key === 'verified' ? ($val === '1' ? 'Verificado' : 'Sin verificar') : $val }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $users->total() }} usuario{{ $users->total() !== 1 ? 's' : '' }}</p>
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <x-sortable-th column="name">Usuario</x-sortable-th>
                            <x-sortable-th column="email">Email</x-sortable-th>
                            <th>Roles</th>
                            <th>Verificación</th>
                            <x-sortable-th column="id">Registrado</x-sortable-th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-ink/10 to-ink/5 border border-ink/10 flex items-center justify-center text-[11px] font-black text-ink shrink-0">
                                            {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                                        </div>
                                        <span class="font-bold text-ink text-sm">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td class="text-muted text-sm">{{ $user->email }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($user->roleNames() as $roleName)
                                            @php $roleBadge = $roleBadgeClasses[$roleName] ?? 'border-ink/25 bg-ink/10 text-ink/70'; @endphp
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[9px] font-black uppercase tracking-widest {{ $roleBadge }}">
                                                {{ $roleName }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    @if($user->email_verified_at)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Verificado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/25 bg-amber-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-amber-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span> Pendiente
                                        </span>
                                    @endif
                                </td>
                                <td class="text-muted text-xs">{{ $user->created_at->translatedFormat('d M, Y') }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('users.edit', $user) }}"
                                           class="h-8 w-8 rounded-lg border border-ink/10 bg-ink/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('users.destroy', $user) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar usuario {{ addslashes($user->name) }}?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Eliminar usuario" class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 flex items-center justify-center text-red-500/70 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <svg class="h-12 w-12 text-ink/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin usuarios</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($users as $user)
                    <div class="rounded-2xl border border-ink/8 bg-card p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-ink/8 border border-ink/10 flex items-center justify-center text-sm font-black text-ink">
                                    {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-ink">{{ $user->name }}</p>
                                    <p class="text-[10px] text-muted">{{ $user->email }}</p>
                                </div>
                            </div>
                            @if($user->email_verified_at)
                                <span class="text-[9px] font-black border border-emerald-500/25 bg-emerald-500/10 text-emerald-400 rounded-full px-2 py-0.5 uppercase">Verificado</span>
                            @else
                                <span class="text-[9px] font-black border border-amber-500/25 bg-amber-500/10 text-amber-400 rounded-full px-2 py-0.5 uppercase">Pendiente</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1.5 border-t border-ink/5 pt-3 mb-3">
                            @foreach($user->roleNames() as $roleName)
                                <span class="text-[9px] font-black bg-ink/8 border border-ink/10 text-muted rounded-full px-2 py-0.5 uppercase">{{ $roleName }}</span>
                            @endforeach
                        </div>
                        <div class="flex justify-end gap-3 border-t border-ink/5 pt-3">
                            <a href="{{ route('users.edit', $user) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-ink transition">Editar</a>
                            <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('¿Eliminar?');">
                                @csrf @method('DELETE')
                                <button class="text-[10px] font-black uppercase tracking-widest text-red-500">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink/10 p-10 text-center"><p class="text-sm text-muted">Sin usuarios.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $users->links() }}</div>
        </section>
    </div>
</x-app-layout>
