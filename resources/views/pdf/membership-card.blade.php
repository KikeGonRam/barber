<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
@php
    $accent = ['nuevo'=>'#c9ccd4','regular'=>'#7fb0e6','vip'=>'#d4af37','leyenda'=>'#e879f9'][$nivel] ?? '#d4af37';
@endphp
<style>
    @page { margin: 0; }
    * { font-family: DejaVu Sans, sans-serif; }
    html, body { margin: 0; padding: 0; }
    body { background: #0d0d0d; color: #f4f4f2; }
    .card { width: 100%; height: 100%; box-sizing: border-box; padding: 22px 26px;
            background: #16130c; border: 2px solid {{ $accent }}; }
    .brand { font-size: 17px; font-weight: bold; text-transform: uppercase; letter-spacing: -0.5px; color: #fff; }
    .brand span { color: #d4af37; }
    .tag { font-size: 7px; letter-spacing: 2px; text-transform: uppercase; color: #7a7a7a; margin-top: 1px; }
    .badge { color: {{ $accent }}; border: 1px solid {{ $accent }}; border-radius: 20px; padding: 4px 12px;
             font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; }
    .lab { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #8a8a8a; }
    .holder { font-size: 17px; font-weight: bold; text-transform: uppercase; color: #fff; margin-top: 2px; }
    .num { font-family: DejaVu Sans Mono, monospace; font-size: 11px; letter-spacing: 2px; color: rgba(255,255,255,0.72); margin-top: 6px; }
    .pts { font-size: 22px; font-weight: bold; color: #d4af37; }
    .qrbox { background: #fff; border-radius: 8px; padding: 6px; width: 104px; height: 104px; }
    .qrbox img { width: 100%; height: 100%; }
    .qrhint { font-size: 6.5px; text-transform: uppercase; letter-spacing: 1px; color: #8a8a8a; text-align: center; margin-top: 5px; }
    .bene { font-size: 8.5px; padding: 2px 0; }
    .bene .dot { color: #d4af37; font-weight: bold; }
    .bene.off { color: #555; }
    .bene.off .dot { color: #444; }
</style>
</head>
<body>
<div class="card">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="brand">Urban<span>Blade</span></div>
                <div class="tag">Elite Grooming Studio</div>
            </td>
            <td align="right"><span class="badge">{{ $label }}</span></td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 18px;">
        <tr>
            <td valign="top" width="62%">
                <div class="lab">Socio</div>
                <div class="holder">{{ $nombre }}</div>
                <div class="num">{{ $numero }}</div>

                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 14px;">
                    <tr>
                        <td valign="bottom">
                            <div class="lab">Miembro desde</div>
                            <div style="font-size:13px;font-weight:bold;">{{ $desde }}</div>
                        </td>
                        <td valign="bottom">
                            <div class="lab">Puntos</div>
                            <div class="pts">{{ number_format($puntos) }}</div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 14px;">
                    <div class="lab" style="margin-bottom: 4px;">Beneficios</div>
                    @foreach($beneficios as $b)
                        <div class="bene {{ $b['on'] ? '' : 'off' }}"><span class="dot">{{ $b['on'] ? '&#10003;' : '&#8226;' }}</span> {{ $b['text'] }}</div>
                    @endforeach
                </div>
            </td>
            <td valign="top" align="right" width="38%">
                @if($qr)
                    <div class="qrbox"><img src="{{ $qr }}" alt="QR"></div>
                    <div class="qrhint">Presenta en recepcion</div>
                @endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>
