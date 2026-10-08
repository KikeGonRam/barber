@php
    $shop = \App\Services\Mail\ShopBranding::contact();
    $site = \App\Services\Mail\ShopBranding::frontendUrl();
    $contactLine = collect([$shop['direccion'], $shop['telefono'], $shop['email']])->filter()->implode('  ·  ');
@endphp
@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => $site])
            <div class="brand-word">URBAN<span class="brand-blade">BLADE</span></div>
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
<div class="foot-brand">URBAN<span style="color:#d4af37;">BLADE</span></div>
@if($contactLine !== '')
<div class="foot-contact">{{ $contactLine }}</div>
@endif
<div class="foot-social"><a href="{{ $site }}">urbanblade.com.mx</a></div>
<div class="foot-legal">Recibes este correo porque tienes una cuenta en {{ config('app.name') }}. &nbsp;·&nbsp; <a href="{{ $site }}/notifications">Preferencias de correo</a></div>
<div class="foot-legal" style="margin-top:6px;">© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</div>
        @endcomponent
    @endslot
@endcomponent
