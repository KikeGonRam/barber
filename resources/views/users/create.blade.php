<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Alta de <span class="text-gold">Nuevo Miembro</span></h2>
                <p class="ui-subtitle">Incorpora talento al equipo o crea accesos administrativos.</p>
            </div>
            <a href="{{ route('users.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('users.store') }}" class="space-y-10" x-data="{ role: '{{ old('role', '') }}' }">
                    @csrf

                    <!-- Section: Identity -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Identidad del Usuario</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="group">
                                <label class="ui-label" for="name">Nombre Completo</label>
                                <input id="name" name="name" value="{{ old('name') }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" 
                                       placeholder="Ej: Alessandro Volte" required>
                                @error('name') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div class="group">
                                <label class="ui-label" for="email">Correo Electrónico</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" 
                                       placeholder="staff@urbanblade.com" required>
                                @error('email') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Role Assignment -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Asignación de Rol & Permisos</h3>
                        </div>

                        <input type="hidden" name="role" x-model="role">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @php
                                $roleInfo = [
                                    'administrador' => ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'desc' => 'Control total del sistema y finanzas.'],
                                    'recepcionista' => ['icon' => 'M19 21v-2a4 4 0 00-4-4H9a4 4 0 00-4 4v2', 'desc' => 'Gestión de agenda y cobros diarios.'],
                                    'barbero' => ['icon' => 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121', 'desc' => 'Acceso a agenda personal y perfil.'],
                                    'cliente' => ['icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'desc' => 'Usuario básico con historial de citas.'],
                                ];
                            @endphp

                            @foreach($roles as $r)
                                <button type="button" 
                                        @click="role = '{{ $r }}'"
                                        :class="role === '{{ $r }}' ? 'border-gold bg-gold/10 text-gold shadow-[0_0_20px_rgba(212,175,55,0.1)]' : 'border-white/5 bg-white/5 text-muted'"
                                        class="flex flex-col text-left p-5 rounded-2xl border transition-all hover:border-gold/30 group">
                                    <div class="h-10 w-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center mb-4 group-hover:text-gold transition-colors">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $roleInfo[$r]['icon'] ?? 'M12 4v16m8-8H4' }}" /></svg>
                                    </div>
                                    <p class="text-[11px] font-black uppercase tracking-widest mb-1">{{ ucfirst($r) }}</p>
                                    <p class="text-[9px] font-bold text-muted leading-tight">{{ $roleInfo[$r]['desc'] ?? 'Sin descripción.' }}</p>
                                </button>
                            @endforeach
                        </div>
                        @error('role') <p class="mt-4 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                    </div>

                    <!-- Section: Security -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Seguridad de la Cuenta</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="group">
                                <label class="ui-label" for="password">Contraseña Provisional</label>
                                <input id="password" type="password" name="password" 
                                       class="ui-input !bg-panel border-white/10 text-white" 
                                       placeholder="••••••••" required>
                                @error('password') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div class="group">
                                <label class="ui-label" for="password_confirmation">Repetir Contraseña</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" 
                                       class="ui-input !bg-panel border-white/10 text-white" 
                                       placeholder="••••••••" required>
                            </div>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Crear Miembro del Equipo <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
