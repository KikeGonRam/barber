@props(['class' => ''])

<div x-data="darkModeToggle()" @init="initDarkMode()" class="relative group {{ $class }}">
    <!-- Toggle Button -->
    <button
        @click="toggleDarkMode()"
        title="Toggle Dark Mode (Ctrl+Shift+D)"
        aria-label="Toggle dark mode"
        class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300"
        :class="isDark ? 'bg-white/10 hover:bg-white/20 text-yellow-400' : 'bg-white/5 hover:bg-white/10 text-blue-400'"
    >
        <!-- Sun Icon (Light Mode) -->
        <svg
            x-show="!isDark"
            xmlns="http://www.w3.org/2000/svg"
            class="w-5 h-5 transition-transform duration-300"
            fill="currentColor"
            viewBox="0 0 24 24"
        >
            <circle cx="12" cy="12" r="5"/>
            <line x1="12" y1="1" x2="12" y2="3"/>
            <line x1="12" y1="21" x2="12" y2="23"/>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
            <line x1="1" y1="12" x2="3" y2="12"/>
            <line x1="21" y1="12" x2="23" y2="12"/>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>

        <!-- Moon Icon (Dark Mode) -->
        <svg
            x-show="isDark"
            xmlns="http://www.w3.org/2000/svg"
            class="w-5 h-5 transition-transform duration-300"
            fill="currentColor"
            viewBox="0 0 24 24"
        >
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>

    <!-- Tooltip -->
    <div
        class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1 text-xs text-white bg-black/80 rounded-md whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50"
    >
        <span x-text="isDark ? 'Modo Claro' : 'Modo Oscuro'"></span>
        <div class="text-[10px] text-gray-400">Ctrl+Shift+D</div>
    </div>
</div>

<script>
function darkModeToggle() {
    return {
        isDark: false,
        
        initDarkMode() {
            const saved = localStorage.getItem('darkMode');
            if (saved !== null) {
                this.isDark = saved === 'true';
            } else {
                this.isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }
            
            if (this.isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // Register keyboard shortcut
            this.registerShortcut();
        },
        
        toggleDarkMode() {
            this.isDark = !this.isDark;
            localStorage.setItem('darkMode', this.isDark ? 'true' : 'false');
            
            if (this.isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        },
        
        registerShortcut() {
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
                    e.preventDefault();
                    this.toggleDarkMode();
                }
            });
        }
    };
}
</script>

