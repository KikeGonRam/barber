<section>
    <header class="mb-6">
        <h2 class="text-lg font-black text-white uppercase tracking-tight">
            {{ __('Apariencia') }}
        </h2>

        <p class="mt-1 text-xs text-muted font-medium">
            {{ __('Elige el tema del panel. Se aplica solo a tu cuenta, en cualquier dispositivo donde inicies sesión.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.theme.update') }}">
        @csrf
        @method('patch')

        @php
            $currentTheme = auth()->user()->theme ?? 'noir';
            $themes = [
                'noir' => ['name' => 'Sastrería Nocturna', 'desc' => 'Negro + oro', 'bg' => '#0a0a0a', 'card' => '#161616', 'accent' => '#d4af37'],
                'acero' => ['name' => 'Taller de Acero', 'desc' => 'Grafito + cobre', 'bg' => '#111317', 'card' => '#1a1d22', 'accent' => '#c1703d'],
                'salon' => ['name' => 'Salón Inglés', 'desc' => 'Verde inglés + latón', 'bg' => '#0b1210', 'card' => '#141c19', 'accent' => '#c9a24a'],
                'libreta' => ['name' => 'Libreta de Barbero', 'desc' => 'Marfil + tinta + oro', 'bg' => '#f3ede0', 'card' => '#fffbf3', 'accent' => '#b8860b'],
            ];
        @endphp

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($themes as $value => $t)
                <label class="group relative block cursor-pointer">
                    <input
                        type="radio"
                        name="theme"
                        value="{{ $value }}"
                        class="peer sr-only"
                        onchange="this.form.submit()"
                        @checked($currentTheme === $value)
                    >
                    <div
                        class="rounded-xl border-2 p-3 transition-all peer-checked:border-gold peer-checked:shadow-lg peer-checked:shadow-gold/10"
                        style="border-color: {{ $currentTheme === $value ? '' : 'rgba(255,255,255,0.08)' }};"
                    >
                        <div class="flex h-14 overflow-hidden rounded-lg" style="background: {{ $t['bg'] }};">
                            <div class="flex-1" style="background: {{ $t['card'] }};"></div>
                            <div class="w-3" style="background: {{ $t['accent'] }};"></div>
                        </div>
                        <p class="mt-2.5 text-[11px] font-black uppercase tracking-wide text-white">{{ $t['name'] }}</p>
                        <p class="text-[10px] text-muted">{{ $t['desc'] }}</p>
                    </div>
                    @if ($currentTheme === $value)
                        <span class="absolute right-2 top-2 flex h-4 w-4 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">✓</span>
                    @endif
                </label>
            @endforeach
        </div>

        @if (session('status') === 'theme-updated')
            <p
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 2000)"
                class="mt-4 text-[10px] font-black uppercase text-gold tracking-widest"
            >{{ __('Tema actualizado.') }}</p>
        @endif
    </form>
</section>
