<section>
    <header class="mb-6">
        <h2 class="text-lg font-black text-ink uppercase tracking-tight">
            {{ __('Información del Perfil') }}
        </h2>

        <p class="mt-1 text-xs text-muted font-medium">
            {{ __("Actualiza la información de tu cuenta y tu dirección de correo electrónico.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        <div class="group">
            <x-input-label for="name" :value="__('Nombre')" class="ui-label" />
            <x-text-input id="name" name="name" type="text" class="ui-input !bg-panel border-ink/10 focus:border-gold/50 text-ink" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2 text-[10px] font-black text-red-500 uppercase" :messages="$errors->get('name')" />
        </div>

        <div class="group">
            <x-input-label for="email" :value="__('Correo Electrónico')" class="ui-label" />
            <x-text-input id="email" name="email" type="email" class="ui-input !bg-panel border-ink/10 focus:border-gold/50 text-ink" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2 text-[10px] font-black text-red-500 uppercase" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-4 p-4 rounded-xl border border-gold/20 bg-gold/5">
                    <p class="text-xs text-gold font-bold">
                        {{ __('Tu dirección de correo electrónico no está verificada.') }}

                        <button form="send-verification" class="ml-2 underline hover:text-ink transition">
                            {{ __('Haz clic aquí para volver a enviar el correo de verificación.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-[10px] font-black text-green-400 uppercase tracking-widest">
                            {{ __('Se ha enviado un nuevo enlace de verificación a tu dirección de correo electrónico.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-ink/5">
            <button type="submit" class="ui-btn px-10">
                {{ __('Guardar Cambios') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-[10px] font-black uppercase text-gold tracking-widest"
                >{{ __('Guardado.') }}</p>
            @endif
        </div>
    </form>
</section>
