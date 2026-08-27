<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Configuración de <span class="text-gold">Perfil</span></h2>
                <p class="ui-subtitle">Administra tu información personal y seguridad de la cuenta.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
            
            <!-- Update Profile Info -->
            <section class="ui-surface">
                <div class="max-w-2xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>

            <!-- Appearance / Theme -->
            <section class="ui-surface">
                <div class="max-w-2xl">
                    @include('profile.partials.update-theme-form')
                </div>
            </section>

            <!-- Update Password -->
            <section class="ui-surface">
                <div class="max-w-2xl">
                    @include('profile.partials.update-password-form')
                </div>
            </section>

            <!-- Danger Zone / Delete Account -->
            <section class="ui-surface border-red-900/20 bg-red-950/5">
                <div class="max-w-2xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
