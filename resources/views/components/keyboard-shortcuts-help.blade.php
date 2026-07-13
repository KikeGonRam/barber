@props(['class' => ''])

<div x-data="keyboardShortcutsHelp()" @init="init()" class="{{ $class }}">
    <!-- Button to open modal -->
    <button
        @click="showModal = true"
        title="Help & Keyboard Shortcuts (Ctrl+?)"
        aria-label="Show help and keyboard shortcuts"
        class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 bg-white/5 hover:bg-white/10 text-blue-400"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
        </svg>
    </button>

    <!-- Modal Backdrop -->
    <div
        x-show="showModal"
        @click="showModal = false"
        class="fixed inset-0 bg-black/50 z-40 transition-opacity"
        style="display: none;"
    ></div>

    <!-- Modal -->
    <div
        x-show="showModal"
        @click.stop
        class="fixed inset-0 flex items-center justify-center z-50 p-4 transition-opacity"
        style="display: none;"
    >
        <div class="bg-gray-900 rounded-lg shadow-2xl max-w-2xl w-full max-h-96 overflow-y-auto border border-white/10">
            <!-- Header -->
            <div class="sticky top-0 bg-indigo-600 text-white px-6 py-4 flex items-center justify-between">
                <h2 class="text-lg font-bold flex items-center gap-2">
                    Atajos de Teclado
                </h2>
                <button
                    @click="showModal = false"
                    class="text-white hover:text-gray-300 transition text-xl"
                    aria-label="Cerrar"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6">
                <!-- Categoría: Navegación -->
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-gold mb-3 uppercase tracking-wider">Navegación</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Dashboard</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+Alt+D</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Citas</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+Alt+A</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Clientes</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+Alt+C</kbd>
                        </div>
                    </div>
                </div>

                <!-- Categoría: Herramientas -->
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-gold mb-3 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Herramientas
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Buscar / Comando</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+K</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Dark Mode</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+Shift+D</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Ayuda</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+?</kbd>
                        </div>
                    </div>
                </div>

                <!-- Categoría: Edición -->
                <div>
                    <h3 class="text-sm font-bold text-gold mb-3 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edición
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Guardar</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Ctrl+S</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Cancelar</span>
                            <kbd class="px-2 py-1 bg-white/10 rounded font-mono text-xs text-white border border-white/20">Escape</kbd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-white/10 bg-black/20 px-6 py-3">
                <p class="text-xs text-muted">
                    <svg class="inline h-3 w-3 mr-1 text-gold/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tip: Usa <kbd class="px-1 bg-white/10 rounded font-mono text-[10px]">Ctrl+K</kbd> para buscar cualquier página
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function keyboardShortcutsHelp() {
    return {
        showModal: false,
        
        init() {
            // Register keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Ctrl+? or Cmd+? on Mac
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === '?') {
                    e.preventDefault();
                    this.showModal = !this.showModal;
                }
            });
        }
    };
}
</script>

