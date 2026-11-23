@php
    // Plantilla de correo con marca reutilizada por todas las notificaciones.
    // Parametros: accent (hex), badge, title, greeting, intro, code, rows (k=>v),
    // total (['label'=>,'value'=>]), ctaLabel, ctaUrl, secondary, preheader.
    $accent = $accent ?? '#d4af37';
    // Texto de vista previa: lo que Gmail/Outlook muestran junto al asunto en la bandeja. Sin él mostraban
    // «CITA CONFIRMADA Tu cita…» (la etiqueta y el título). Por defecto es la introducción, sin Markdown.
    $preheader = $preheader ?? \Illuminate\Support\Str::limit(
        trim(preg_replace('/\s+/', ' ', strip_tags(\Illuminate\Support\Str::markdown((string) ($intro ?? $title ?? ''))))),
        110,
    );
@endphp
@component('mail::message')
<div class="preheader" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $preheader }}</div>
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

@isset($code)
<div class="code-box" style="border:1px solid {{ $accent }};">{{ $code }}</div>
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
@component('mail::button', ['url' => \App\Services\Mail\ShopBranding::smartLink((string) $ctaUrl), 'color' => 'primary'])
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
