<x-guest-layout>
    <div class="mb-8 text-center">
        <h2 class="text-xl font-black uppercase tracking-widest text-white">Recuperar <span class="text-gold italic font-serif tracking-normal lowercase">acceso</span></h2>
        <p class="mt-4 text-[10px] font-bold uppercase leading-relaxed tracking-widest text-muted">
            ¿Olvidaste tu contraseña? No hay problema. Introduce tu email y te enviaremos un enlace para elegir una nueva.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-6 rounded-xl border border-gold/30 bg-gold/5 p-3 text-center text-xs font-bold text-gold" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <!-- Email Address -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                Correo Electrónico
            </label>
            <input id="email" type="email" name="email" :value="old('email')" 
                   class="ui-input !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autofocus placeholder="tu@email.com">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <div class="pt-2">
            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                Enviar Enlace de Recuperación
            </button>
        </div>

        <div class="text-center pt-4">
            <a href="{{ route('login') }}" class="text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition">
                &larr; Volver al inicio de sesión
            </a>
        </div>
    </form>
</x-guest-layout>
