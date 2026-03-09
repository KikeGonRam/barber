<x-guest-layout>
    <!-- Title -->
    <div class="mb-8 text-center">
        <h2 class="text-xl font-black uppercase tracking-widest text-white">Bienvenido <span class="text-gold italic font-serif tracking-normal lowercase">de nuevo</span></h2>
        <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-muted">Introduce tus credenciales para continuar</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-6 rounded-xl border border-gold/30 bg-gold/5 p-3 text-center text-xs font-bold text-gold" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <!-- Email Address -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                Correo Electrónico
            </label>
            <input id="email" type="email" name="email" :value="old('email')" 
                   class="ui-input !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autofocus autocomplete="username" placeholder="tu@email.com">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <!-- Password -->
        <div class="group">
            <div class="flex items-center justify-between mb-1">
                <label class="ui-label flex items-center gap-2">
                    <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    Contraseña
                </label>
                @if (Route::has('password.request'))
                    <a class="text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition" href="{{ route('password.request') }}">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <input id="password" type="password" name="password" 
                   class="ui-input !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autocomplete="current-password" placeholder="••••••••">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex cursor-pointer items-center group">
                <div class="relative flex items-center">
                    <input id="remember_me" type="checkbox" class="peer h-4 w-4 rounded border-white/10 bg-black/40 text-gold shadow-sm focus:ring-gold/30 focus:ring-offset-0 focus:ring-opacity-50 transition-all" name="remember">
                </div>
                <span class="ms-2 text-[10px] font-bold uppercase tracking-widest text-muted group-hover:text-ink transition-colors">Recordarme en este equipo</span>
            </label>
        </div>

        <!-- Actions -->
        <div class="pt-2">
            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                Iniciar Sesión <span class="ml-2 opacity-50">&rarr;</span>
            </button>
        </div>

        <!-- Register Link -->
        <div class="text-center pt-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-muted">
                ¿Aún no tienes cuenta? 
                <a href="{{ route('register') }}" class="text-gold hover:underline ml-1">Regístrate ahora</a>
            </p>
        </div>
    </form>
</x-guest-layout>
