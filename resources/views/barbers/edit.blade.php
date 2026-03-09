<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestionar <span class="text-gold">Perfil de Barbero</span></h2>
                <p class="ui-subtitle">Administra la información profesional y disponibilidad del equipo.</p>
            </div>
            <a href="{{ route('barbers.index') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                &larr; Volver al equipo
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="ui-surface">
                <form method="POST" action="{{ route('barbers.update', $barber) }}" class="space-y-10">
                    @csrf
                    @method('PUT')

                    <!-- Section: Account & Identity -->
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Identidad & Cuenta</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="ui-label" for="name">Nombre del Barbero</label>
                                <input id="name" name="name" value="{{ old('name', $barber->user?->name) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('name') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="ui-label" for="email">Email Corporativo</label>
                                <input id="email" type="email" name="email" value="{{ old('email', $barber->user?->email) }}" class="ui-input !bg-panel border-white/10 text-white" required>
                                @error('email') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Professional Info -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.54 1.118l-3.976-2.888a1 1 0 00-1.175 0l-3.976 2.888c-.784.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Perfil Profesional</h3>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="ui-label" for="especialidades">Especialidades & Skills</label>
                                <input id="especialidades" name="especialidades" value="{{ old('especialidades', $barber->especialidades) }}" 
                                       class="ui-input !bg-panel border-white/10 text-white" placeholder="Ej: Fade, Color, Barba tradicional">
                                @error('especialidades') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="ui-label" for="descripcion">Biografía / Perfil Público</label>
                                <textarea id="descripcion" name="descripcion" rows="4" 
                                          class="ui-input !bg-panel border-white/10 text-white leading-relaxed">{{ old('descripcion', $barber->descripcion) }}</textarea>
                                @error('descripcion') <p class="mt-2 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section: Status & Photo -->
                    <div class="pt-8 border-t border-white/5">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold/10 flex items-center justify-center text-gold">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Estatus Operativo</h3>
                        </div>

                        <div class="flex flex-col md:flex-row items-center gap-10">
                            <div class="flex-1 w-full">
                                <label class="ui-label" for="foto">Ruta de Foto de Perfil</label>
                                <input id="foto" name="foto" value="{{ old('foto', $barber->foto) }}" class="ui-input !bg-panel border-white/10 text-white">
                                <p class="mt-2 text-[9px] text-muted uppercase font-bold tracking-widest italic">Ruta relativa a storage/public.</p>
                            </div>

                            <label class="relative inline-flex cursor-pointer items-center group">
                                <input type="checkbox" name="activo" value="1" class="sr-only peer" @checked(old('activo', $barber->activo))>
                                <div class="h-6 w-11 rounded-full bg-white/5 border border-white/10 peer-checked:bg-gold/20 peer-checked:border-gold transition-all"></div>
                                <div class="absolute left-1 top-1 h-4 w-4 rounded-full bg-muted peer-checked:bg-gold peer-checked:translate-x-5 transition-all"></div>
                                <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-muted group-hover:text-white transition-colors">Barbero Activo para Citas</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-white/5 flex justify-end">
                        <button type="submit" class="ui-btn px-16 py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                            Actualizar Ficha Profesional <span class="ml-2 opacity-50">&rarr;</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
