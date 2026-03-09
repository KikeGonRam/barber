<section class="space-y-6">
    <header>
        <h2 class="text-lg font-black text-white uppercase tracking-tight">
            {{ __('Eliminar Cuenta') }}
        </h2>

        <p class="mt-1 text-xs text-muted font-medium">
            {{ __('Una vez que se elimine tu cuenta, todos sus recursos y datos se eliminarán permanentemente. Antes de eliminar tu cuenta, descarga cualquier dato o información que desees conservar.') }}
        </p>
    </header>

    <x-danger-button
        class="rounded-xl border border-red-900/50 bg-red-950/10 py-3 px-6 text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500 hover:text-white transition-all"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Eliminar Cuenta') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-8 ui-card-premium border-red-900/20">
            @csrf
            @method('delete')

            <h2 class="text-lg font-black text-white uppercase tracking-tight">
                {{ __('¿Estás seguro de que quieres eliminar tu cuenta?') }}
            </h2>

            <p class="mt-2 text-xs text-muted font-medium">
                {{ __('Una vez que se elimine tu cuenta, todos sus recursos y datos se eliminarán permanentemente. Por favor, introduce tu contraseña para confirmar que deseas eliminar tu cuenta de forma permanente.') }}
            </p>

            <div class="mt-6 group">
                <x-input-label for="password" value="{{ __('Contraseña') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="ui-input !bg-panel border-white/10 focus:border-red-500/50 text-white"
                    placeholder="{{ __('Introduce tu contraseña') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-[10px] font-black text-red-500 uppercase" />
            </div>

            <div class="mt-8 flex justify-end gap-4">
                <button type="button" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </button>

                <button type="submit" class="rounded-xl border border-red-900/50 bg-red-500 py-2.5 px-6 text-[10px] font-black uppercase tracking-widest text-white hover:bg-red-600 transition-all">
                    {{ __('Eliminar Definitivamente') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
