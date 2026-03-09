<x-guest-layout>
    <div class="mb-8 text-center">
        <h2 class="text-xl font-black uppercase tracking-widest text-white">Verificar <span class="text-gold italic font-serif tracking-normal lowercase">email</span></h2>
        <p class="mt-4 text-[10px] font-bold uppercase leading-relaxed tracking-widest text-muted">
            ¡Gracias por registrarte! Antes de comenzar, ¿podrías verificar tu dirección de correo electrónico haciendo clic en el enlace que acabamos de enviarte? Si no lo recibiste, te enviaremos otro con gusto.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 rounded-xl border border-green-900/50 bg-green-950/20 p-4 text-center text-xs font-bold text-green-400">
            Se ha enviado un nuevo enlace de verificación a la dirección de correo electrónico que proporcionaste durante el registro.
        </div>
    @endif

    <div class="mt-8 flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="ui-btn w-full py-4 text-[11px] uppercase tracking-[0.2em]">
                Reenviar Email de Verificación
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition underline underline-offset-4">
                Cerrar Sesión
            </button>
        </form>
    </div>
</x-guest-layout>
