@php
    // Plantilla de correo con marca reutilizada por todas las notificaciones.
    // Parametros: accent (hex), badge, title, greeting, intro, rows (k=>v),
    // total (['label'=>,'value'=>]), ctaLabel, ctaUrl, secondary.
    $accent = $accent ?? '#d4af37';
@endphp
@component('mail::message')
<div style="height:4px;background-color:{{ $accent }};border-radius:4px;line-height:4px;font-size:4px;margin:0 0 22px;">&nbsp;</div>
@isset($badge)
<span class="badge" style="color:{{ $accent }};border:1px solid {{ $accent }};background-color:#1c1c1c;">{{ $badge }}</span>
@endisset

# {{ $title }}

@isset($greeting)
{{ $greeting }}
@endisset

@isset($intro)
{{ $intro }}
@endisset

@isset($rows)
<table class="detail" cellpadding="0" cellspacing="0" role="presentation" style="border-left:3px solid {{ $accent }};">
@foreach($rows as $k => $v)
<tr><td class="k">{{ $k }}</td><td class="v">{{ $v }}</td></tr>
@endforeach
@isset($total)
<tr class="detail-total"><td class="k">{{ $total['label'] }}</td><td class="v">{{ $total['value'] }}</td></tr>
@endisset
</table>
@endisset

@isset($ctaUrl)
@component('mail::button', ['url' => $ctaUrl, 'color' => 'primary'])
{{ $ctaLabel ?? 'Ver' }}
@endcomponent
@endisset

@isset($secondary)
{{ $secondary }}
@endisset
@isset($pixel)
<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:none;border:0;">
@endisset
@endcomponent
