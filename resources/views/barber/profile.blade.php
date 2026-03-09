<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Perfil de <span class="text-gold">Maestro Barbero</span></h2>
                <p class="ui-subtitle">Personaliza tu presencia digital y cómo te ven tus clientes.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="ui-badge border-green-500/20 bg-green-500/5 text-green-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                    Perfil Activo
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Perfil Visual (Preview) -->
                <aside class="space-y-6">
                    <div class="ui-card-premium overflow-hidden border-gold/20 p-0 shadow-2xl shadow-gold/5">
                        <div class="h-32 bg-gradient-to-br from-indigo-900 to-purple-900 relative">
                            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
                        </div>
                        <div class="relative px-6 pb-8 text-center -mt-16">
                            <div class="inline-block relative">
                                <div class="h-32 w-32 rounded-full border-4 border-bg-card bg-bg-accent shadow-xl overflow-hidden mx-auto">
                                    @if($barber->foto)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center text-3xl font-black text-gold/30">
                                            {{ substr(auth()->user()->name, 0, 2) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="absolute bottom-1 right-1 h-6 w-6 rounded-full bg-green-500 border-4 border-bg-card shadow-lg" title="En línea"></div>
                            </div>
                            
                            <h3 class="mt-4 text-xl font-black text-white uppercase tracking-tight">{{ auth()->user()->name }}</h3>
                            <p class="text-xs font-bold text-gold uppercase tracking-[0.2em] mt-1">{{ $barber->especialidades ?: 'Maestro Groomer' }}</p>
                            
                            <div class="mt-6 flex justify-center gap-4 border-t border-white/5 pt-6">
                                <div class="text-center">
                                    <p class="text-lg font-black text-white">4.9</p>
                                    <p class="text-[9px] uppercase tracking-widest text-muted">Rating</p>
                                </div>
                                <div class="h-8 w-px bg-white/5"></div>
                                <div class="text-center">
                                    <p class="text-lg font-black text-white">124</p>
                                    <p class="text-[9px] uppercase tracking-widest text-muted">Citas</p>
                                </div>
                                <div class="h-8 w-px bg-white/5"></div>
                                <div class="text-center">
                                    <p class="text-lg font-black text-white">8y</p>
                                    <p class="text-[9px] uppercase tracking-widest text-muted">Exp</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ui-card p-6 border-white/5 bg-black/20">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-muted mb-4">Información de Cuenta</h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] uppercase text-gold/50 font-bold">Email Corporativo</p>
                                <p class="text-sm text-white font-medium">{{ auth()->user()->email }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase text-gold/50 font-bold">Miembro desde</p>
                                <p class="text-sm text-white font-medium">{{ auth()->user()->created_at->format('M Y') }}</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Formulario de Edición -->
                <main class="lg:col-span-2">
                    <section class="ui-surface">
                        <div class="mb-8 border-b border-white/10 pb-6">
                            <h3 class="text-lg font-black text-white uppercase tracking-tight">Editar Perfil Profesional</h3>
                            <p class="text-xs text-muted mt-1 font-medium">Actualiza tu biografía y especialidades para atraer más clientes.</p>
                        </div>

                        <form method="POST" action="{{ route('barber.profile.update') }}" enctype="multipart/form-data" class="space-y-8">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 gap-8">
                                <!-- Especialidades -->
                                <div>
                                    <label class="ui-label flex items-center gap-2">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.54 1.118l-3.976-2.888a1 1 0 00-1.175 0l-3.976 2.888c-.784.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                                        Especialidades
                                    </label>
                                    <input type="text" name="especialidades" value="{{ old('especialidades', $barber->especialidades) }}" 
                                           class="ui-input !bg-panel border-white/10 focus:border-gold/50 text-white" 
                                           placeholder="Ej: Fade, Barba tradicional, Colorimetría...">
                                    @error('especialidades') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                                    <p class="mt-2 text-[9px] text-muted uppercase font-bold tracking-widest italic">Separa tus habilidades con comas.</p>
                                </div>

                                <!-- Biografía -->
                                <div>
                                    <label class="ui-label flex items-center gap-2">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        Sobre mí (Biografía)
                                    </label>
                                    <textarea name="descripcion" rows="5" 
                                              class="ui-input !bg-panel border-white/10 focus:border-gold/50 text-white leading-relaxed" 
                                              placeholder="Cuéntales a tus clientes tu trayectoria y estilo...">{{ old('descripcion', $barber->descripcion) }}</textarea>
                                    @error('descripcion') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                                </div>

                                <!-- Foto de Perfil -->
                                <div>
                                    <label class="ui-label flex items-center gap-2">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        Nueva Foto de Perfil
                                    </label>
                                    <div class="mt-2 flex items-center gap-6">
                                        <div class="relative group cursor-pointer">
                                            <input type="file" name="foto" id="foto-input" accept=".jpg,.jpeg,.png,.webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                            <div class="h-20 w-20 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-gold group-hover:bg-gold group-hover:text-black transition-all">
                                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" /></svg>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs text-white font-medium">Sube una imagen cuadrada</p>
                                            <p class="text-[10px] text-muted uppercase font-bold mt-1">Recomendado: 500x500px (JPG, PNG, WEBP)</p>
                                        </div>
                                    </div>
                                    @error('foto') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="pt-8 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <a href="{{ route('barber.agenda') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">Descartar cambios</a>
                                <button type="submit" class="ui-btn w-full sm:w-auto px-12 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                                    Guardar Perfil <span class="ml-2 opacity-50">&rarr;</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Portfolio Section (Surprise) -->
                    <section class="mt-8 ui-card-premium p-8 border-white/5 bg-black/20">
                        <div class="mb-8 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-black text-white uppercase tracking-tight">Galería de <span class="text-gold">Trabajos</span></h3>
                                <p class="text-xs text-muted mt-1 font-medium">Exhibición visual de tus mejores cortes y estilos.</p>
                            </div>
                            <button class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-gold hover:text-black transition-all">
                                + Subir Trabajo
                            </button>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @for($i=1; $i<=6; $i++)
                                <div class="aspect-square rounded-2xl bg-white/5 border border-white/10 overflow-hidden relative group cursor-pointer text-center flex flex-col items-center justify-center">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-4 text-left">
                                        <p class="text-[10px] font-black text-gold uppercase">Estilo #{{ $i }}</p>
                                        <p class="text-[8px] text-white uppercase font-bold">Fade & Beard</p>
                                    </div>
                                    <svg class="h-10 w-10 text-white/10 group-hover:text-gold/20 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                            @endfor
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>
</x-app-layout>
