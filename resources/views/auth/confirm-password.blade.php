<x-guest-layout>
    <div class="mb-8 text-center">
        <h2 class="text-xl font-black uppercase tracking-widest text-white">Confirmar <span class="text-gold italic font-serif tracking-normal lowercase">seguridad</span></h2>
        <p class="mt-4 text-[10px] font-bold uppercase leading-relaxed tracking-widest text-muted">
            Esta es un área segura de la aplicación. Por favor, confirma tu contraseña antes de continuar.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6">
        @csrf

        <!-- Password -->
        <div class="group">
            <label class="ui-label flex items-center gap-2">
                <svg class="h-3 w-3 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                Contraseña
            </label>
            <input id="password" type="password" name="password" 
                   class="ui-input !bg-black/20 focus:!bg-black/40 transition-all border-white/5 group-focus-within:border-gold/30" 
                   required autocomplete="current-password" placeholder="••••••••">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-[10px] font-bold text-red-500 uppercase tracking-widest" />
        </div>

        <div class="pt-2">
            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-gold/20">
                Confirmar Acceso
            </button>
        </div>
    </form>
</x-guest-layout>
