<x-guest-layout>
    <!-- Title -->
    <div class="mb-8 text-center">
        <h2 class="text-xl font-black uppercase tracking-widest text-white">Únete a la <span class="text-gold italic font-serif tracking-normal lowercase">élite</span></h2>
        <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-muted">Crea tu cuenta para gestionar tus citas premium</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Name -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                Nombre Completo
            </label>
                 <input id="name" type="text" name="name" :value="old('name')" 
                     class="ui-input text-white !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autofocus autocomplete="name" placeholder="Ej: Juan Pérez">
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <!-- Email Address -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                Correo Electrónico
            </label>
                 <input id="email" type="email" name="email" :value="old('email')" 
                     class="ui-input text-white !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autocomplete="username" placeholder="tu@email.com">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <!-- Password -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                Contraseña
            </label>
                 <input id="password" type="password" name="password" 
                     class="ui-input text-white !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autocomplete="new-password" placeholder="••••••••">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <!-- Confirm Password -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                Confirmar Contraseña
            </label>
                 <input id="password_confirmation" type="password" name="password_confirmation" 
                     class="ui-input text-white !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autocomplete="new-password" placeholder="Repite tu contraseña">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <!-- Actions -->
        <div class="pt-4">
            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                Crear mi Cuenta
                <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </button>
        </div>

        <!-- Login Link -->
        <div class="text-center pt-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-muted">
                ¿Ya tienes cuenta? 
                <a href="{{ route('login') }}" class="text-gold hover:underline ml-1">Inicia sesión aquí</a>
            </p>
        </div>
    </form>
</x-guest-layout>
