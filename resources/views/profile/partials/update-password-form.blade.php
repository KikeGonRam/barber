<section>
    <header class="mb-6">
        <h2 class="text-lg font-black text-ink uppercase tracking-tight">
            {{ __('Actualizar Contraseña') }}
        </h2>

        <p class="mt-1 text-xs text-muted font-medium">
            {{ __('Asegúrate de que tu cuenta use una contraseña larga y aleatoria para mantener la seguridad.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-6">
        @csrf
        @method('put')

        <div class="group">
            <x-input-label for="update_password_current_password" :value="__('Contraseña Actual')" class="ui-label" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="ui-input !bg-panel border-ink/10 focus:border-gold/50 text-ink" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-[10px] font-black text-red-500 uppercase" />
        </div>

        <div class="group">
            <x-input-label for="update_password_password" :value="__('Nueva Contraseña')" class="ui-label" />
            <x-text-input id="update_password_password" name="password" type="password" class="ui-input !bg-panel border-ink/10 focus:border-gold/50 text-ink" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-[10px] font-black text-red-500 uppercase" />
        </div>

        <div class="group">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmar Contraseña')" class="ui-label" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="ui-input !bg-panel border-ink/10 focus:border-gold/50 text-ink" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-[10px] font-black text-red-500 uppercase" />
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-ink/5">
            <button type="submit" class="ui-btn px-10">
                {{ __('Cambiar Contraseña') }}
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-[10px] font-black uppercase text-gold tracking-widest"
                >{{ __('Actualizado.') }}</p>
            @endif
        </div>
    </form>
</section>
