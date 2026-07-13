@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            <div style="color: #d4af37; font-weight: 900; text-transform: uppercase; font-size: 22px; letter-spacing: -0.03em;">
                Urban<span style="color: #ffffff;">Blade</span>
            </div>
            <div class="tagline">Elite Grooming Studio</div>
        @endcomponent
    @endslot

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        @slot('subcopy')
            @component('mail::subcopy')
                {{ $subcopy }}
            @endcomponent
        @endslot
    @endisset

    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
<div class="foot-brand">Urban<span style="color:#d4af37;">Blade</span></div>
<div class="foot-contact">Av. Reforma 123, CDMX &nbsp;·&nbsp; +52 55 1234 5678 &nbsp;·&nbsp; hola@urbanblade.com</div>
<div class="foot-social"><a href="{{ config('app.url') }}">Instagram</a> &nbsp;·&nbsp; <a href="{{ config('app.url') }}">Facebook</a></div>
<div class="foot-legal">© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados. &nbsp;·&nbsp; <a href="{{ \Illuminate\Support\Facades\Route::has('notifications.preferences') ? route('notifications.preferences') : config('app.url') }}">Preferencias de correo</a></div>
        @endcomponent
    @endslot
@endcomponent
