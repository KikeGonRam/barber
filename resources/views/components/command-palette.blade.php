<div
    x-data="{ 
        open: false,
        search: '',
        commands: [
            { id: 1, name: 'Nueva Cita', icon: 'M12 4v16m8-8H4', url: '{{ route('appointments.create') }}', shortcut: 'N' },
            { id: 7, name: 'Muro Inspiración', icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', url: '{{ route('social.feed') }}', shortcut: 'I' },
            { id: 2, name: 'Emitir Factura', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', url: '{{ route('payments.create') }}', shortcut: 'F' },
            { id: 3, name: 'Ver Agenda', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', url: '{{ route('appointments.index') }}', shortcut: 'A' },
            { id: 4, name: 'Lista de Clientes', icon: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2', url: '{{ route('clients.index') }}', shortcut: 'C' },
            { id: 5, name: 'Dashboard Principal', icon: 'M3 12l9-8 9 8v8a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z', url: '{{ route('dashboard') }}', shortcut: 'D' },
            { id: 6, name: 'Mi Perfil', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', url: '{{ route('profile.edit') }}', shortcut: 'U' }
        ],
        get filteredCommands() {
            if (this.search === '') return this.commands;
            return this.commands.filter(c => c.name.toLowerCase().includes(this.search.toLowerCase()));
        },
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.open = true;
                }
                if (e.key === 'Escape') this.open = false;
            });
        }
    }"
    x-show="open"
    class="fixed inset-0 z-[200] overflow-y-auto p-4 sm:p-6 md:p-20"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    <!-- Overlay -->
    <div 
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
    ></div>

    <!-- Palette -->
    <div 
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="mx-auto max-w-2xl transform divide-y divide-white/5 overflow-hidden rounded-2xl bg-bg-card shadow-2xl ring-1 ring-white/10 transition-all border border-white/5"
    >
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-gold" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
            </svg>
            <input 
                x-model="search"
                type="text" 
                class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-white placeholder-muted focus:ring-0 sm:text-sm" 
                placeholder="Busca un comando... (Ctrl + K)"
                @keydown.escape="open = false"
                x-ref="searchInput"
                x-effect="if (open) $nextTick(() => $refs.searchInput.focus())"
            >
        </div>

        <ul class="max-h-96 scroll-py-3 overflow-y-auto p-3 custom-scrollbar" x-show="filteredCommands.length > 0">
            <template x-for="command in filteredCommands" :key="command.id">
                <li>
                    <a 
                        :href="command.url" 
                        class="group flex cursor-default select-none items-center rounded-xl p-3 hover:bg-gold/10 transition-all"
                    >
                        <div class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-white/5 border border-white/5 text-gold group-hover:bg-gold group-hover:text-black transition-all">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="command.icon" />
                            </svg>
                        </div>
                        <div class="ml-4 flex-auto">
                            <p class="text-sm font-black text-white uppercase tracking-widest" x-text="command.name"></p>
                            <p class="text-[10px] text-muted font-bold uppercase tracking-tighter" x-text="'Acceso rápido a ' + command.name.toLowerCase()"></p>
                        </div>
                        <div class="ml-4 flex-none">
                            <kbd class="font-sans text-xs font-bold text-gold/40 border border-gold/20 rounded px-1.5 py-0.5" x-text="command.shortcut"></kbd>
                        </div>
                    </a>
                </li>
            </template>
        </ul>

        <div x-show="filteredCommands.length === 0" class="px-6 py-14 text-center sm:px-14">
            <svg class="mx-auto h-6 w-6 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <p class="mt-4 text-sm text-white font-bold">No se encontraron resultados para "<span x-text="search"></span>"</p>
            <p class="mt-2 text-xs text-muted font-medium">Prueba con otra palabra clave o usa las flechas para navegar.</p>
        </div>

        <div class="flex items-center bg-black/40 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted">
            Presiona <kbd class="mx-1.5 font-sans text-gold">Esc</kbd> para cerrar
        </div>
    </div>
</div>
