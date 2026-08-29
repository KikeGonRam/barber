<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Editar <span class="text-gold">{{ explode(' ', $barber->user?->name ?? 'Barbero')[0] }}</span></h2>
                <p class="ui-subtitle">Información profesional, foto y estado operativo.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('barbers.performance', $barber) }}"
                   class="flex items-center gap-1.5 rounded-xl border border-ink/10 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold hover:border-gold/30 transition-all">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Ver Stats
                </a>
                <a href="{{ route('barbers.index') }}"
                   class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-ink transition-all">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Equipo
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="mx-auto max-w-5xl">

            <form method="POST"
                  action="{{ route('barbers.update', $barber) }}"
                  enctype="multipart/form-data"
                  class="grid grid-cols-1 gap-6 lg:grid-cols-3"
                  x-data="{ fileName: '' }">
                @csrf @method('PUT')

                {{-- ── SIDEBAR: AVATAR + ESTADO ──────────────── --}}
                <aside class="space-y-5">

                    {{-- Preview foto actual --}}
                    <div class="ui-card-premium p-6 flex flex-col items-center gap-5">
                        <div class="relative">
                            <div class="h-28 w-28 rounded-3xl overflow-hidden border-2 border-ink/10 bg-card" id="avatarPreview">
                                @if($barber->foto)
                                    <img src="{{ str_starts_with($barber->foto, 'http') ? $barber->foto : \Illuminate\Support\Facades\Storage::url($barber->foto) }}"
                                         class="h-full w-full object-cover"
                                         id="avatarImg"
                                         alt="{{ $barber->user?->name }}">
                                @else
                                    <div class="h-full w-full flex items-center justify-center text-3xl font-black text-gold bg-gradient-to-br from-gold/20 to-gold/5" id="avatarPlaceholder">
                                        {{ strtoupper(mb_substr($barber->user?->name ?? 'B', 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            {{-- Estado dot --}}
                            <div class="absolute -bottom-1 -right-1 h-6 w-6 rounded-full border-[3px] border-[#0f0f0f] {{ $barber->activo ? 'bg-emerald-500' : 'bg-ink/20' }}"></div>
                        </div>

                        <div class="text-center">
                            <p class="font-black text-ink text-base">{{ $barber->user?->name }}</p>
                            <p class="text-[10px] text-muted mt-0.5">{{ $barber->user?->email }}</p>
                            @if($barber->especialidades)
                                <div class="flex flex-wrap justify-center gap-1.5 mt-3">
                                    @foreach(array_slice(explode(',', $barber->especialidades), 0, 3) as $esp)
                                        <span class="text-[9px] font-black border border-gold/20 bg-gold/8 text-gold rounded-full px-2 py-0.5 uppercase tracking-wider">
                                            {{ trim($esp) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Upload foto --}}
                        <div class="w-full">
                            <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-2 text-center">
                                {{ $barber->foto ? 'Cambiar foto' : 'Subir foto' }}
                            </label>
                            <label class="flex flex-col items-center gap-2 w-full rounded-xl border border-dashed border-ink/10 bg-black/20 px-4 py-4 cursor-pointer hover:border-gold/30 hover:bg-gold/5 transition-all group">
                                <svg class="h-6 w-6 text-muted group-hover:text-gold transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-[10px] font-black text-muted group-hover:text-gold transition-colors" x-text="fileName || 'JPG · PNG · WEBP'"></span>
                                <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="hidden"
                                       @change="
                                           fileName = $event.target.files[0]?.name || '';
                                           if ($event.target.files[0]) {
                                               const reader = new FileReader();
                                               reader.onload = e => {
                                                   document.getElementById('avatarImg')
                                                       ? (document.getElementById('avatarImg').src = e.target.result)
                                                       : ($el.closest('aside').querySelector('#avatarPreview').innerHTML = '<img src=\''+e.target.result+'\' alt=\'Vista previa del avatar\' class=\'h-full w-full object-cover\' id=\'avatarImg\'>')
                                               };
                                               reader.readAsDataURL($event.target.files[0]);
                                           }
                                       ">
                            </label>
                            @error('foto')<p class="mt-1 text-[10px] font-black text-red-500 uppercase text-center">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Toggle activo --}}
                    <div class="rounded-2xl border border-ink/8 bg-card p-5" x-data="{ active: {{ $barber->activo ? 'true' : 'false' }} }">
                        <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-4">Estado Operativo</p>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <div>
                                <p class="text-sm font-black" :class="active ? 'text-emerald-300' : 'text-muted'">
                                    <span x-text="active ? 'Activo para citas' : 'No disponible'"></span>
                                </p>
                                <p class="text-[9px] text-muted mt-0.5">Aparece en la agenda pública</p>
                            </div>
                            <div class="relative">
                                <input type="checkbox" name="activo" value="1" class="sr-only" x-model="active">
                                <div class="h-7 w-12 rounded-full border transition-all duration-300"
                                     :class="active ? 'bg-emerald-500/20 border-emerald-500/40' : 'bg-ink/5 border-ink/10'">
                                    <div class="absolute top-1.5 h-4 w-4 rounded-full transition-all duration-300"
                                         :class="active ? 'left-7 bg-emerald-400' : 'left-1.5 bg-ink/30'">
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </aside>

                {{-- ── MAIN FORM ────────────────────────────── --}}
                <main class="lg:col-span-2 space-y-5">

                    {{-- Identidad --}}
                    <div class="ui-card-premium p-6">
                        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-ink/5">
                            <div class="h-8 w-8 rounded-xl bg-gold/10 border border-gold/20 flex items-center justify-center">
                                <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Identidad & Cuenta</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Nombre</label>
                                <input name="name" value="{{ old('name', $barber->user?->name) }}"
                                       class="h-11 w-full rounded-xl border border-ink/10 bg-black/30 px-4 text-sm text-ink focus:border-gold/50 focus:outline-none transition-all"
                                       required placeholder="Nombre completo">
                                @error('name')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Email</label>
                                <input type="email" name="email" value="{{ old('email', $barber->user?->email) }}"
                                       class="h-11 w-full rounded-xl border border-ink/10 bg-black/30 px-4 text-sm text-ink focus:border-gold/50 focus:outline-none transition-all"
                                       required placeholder="email@barberia.com">
                                @error('email')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Perfil profesional --}}
                    <div class="ui-card-premium p-6">
                        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-ink/5">
                            <div class="h-8 w-8 rounded-xl bg-gold/10 border border-gold/20 flex items-center justify-center">
                                <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.54 1.118l-3.976-2.888a1 1 0 00-1.175 0l-3.976 2.888c-.784.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <h3 class="text-sm font-black text-ink uppercase tracking-widest">Perfil Profesional</h3>
                        </div>
                        <div class="space-y-5">
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Especialidades</label>
                                <input name="especialidades" value="{{ old('especialidades', $barber->especialidades) }}"
                                       class="h-11 w-full rounded-xl border border-ink/10 bg-black/30 px-4 text-sm text-ink focus:border-gold/50 focus:outline-none transition-all"
                                       placeholder="Ej: Fade, Barba tradicional, Colorimetría...">
                                <p class="mt-1.5 text-[9px] text-muted/60 uppercase tracking-wider">Separa con comas</p>
                                @error('especialidades')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Biografía</label>
                                <textarea name="descripcion" rows="4"
                                          class="w-full rounded-xl border border-ink/10 bg-black/30 px-4 py-3 text-sm text-ink placeholder-ink/20 focus:border-gold/50 focus:outline-none transition-all leading-relaxed resize-none"
                                          placeholder="Describe la trayectoria y estilo del barbero...">{{ old('descripcion', $barber->descripcion) }}</textarea>
                                @error('descripcion')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="flex items-center justify-between gap-4 pt-1">
                        <a href="{{ route('barbers.index') }}"
                           class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-ink transition">
                            Cancelar
                        </a>
                        <button type="submit" class="ui-btn px-10 py-3 text-[11px] uppercase tracking-[0.15em] shadow-lg shadow-gold/20">
                            Guardar Cambios &rarr;
                        </button>
                    </div>
                </main>

            </form>
        </div>
    </div>
</x-app-layout>
