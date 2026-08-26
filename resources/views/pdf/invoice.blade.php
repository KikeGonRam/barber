<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    /* Factura imprimible: fondo claro (a diferencia del correo oscuro). */
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; color: #1a1a1a; font-size: 12px; }
    .sheet { padding: 40px 44px; }
    .top { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: -0.5px; text-transform: uppercase; color: #111; }
    .brand span { color: #b8942e; }
    .brand-sub { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: #999; margin-top: 3px; }
    .doc { text-align: right; }
    .doc .h { font-size: 20px; font-weight: bold; text-transform: uppercase; color: #b8942e; letter-spacing: 1px; }
    .doc .m { font-size: 11px; color: #666; margin-top: 4px; }
    .rule { height: 2px; background: #b8942e; margin: 0 0 24px; }
    .parties { width: 100%; border-collapse: collapse; margin-bottom: 26px; }
    .parties td { vertical-align: top; width: 50%; }
    .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: #999; margin-bottom: 5px; }
    .val { font-size: 13px; font-weight: bold; color: #111; }
    .val small { display: block; font-weight: normal; font-size: 11px; color: #666; margin-top: 2px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.items th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #999; border-bottom: 1.5px solid #ddd; padding: 0 0 8px; }
    table.items th.r, table.items td.r { text-align: right; }
    table.items td { padding: 11px 0; border-bottom: 1px solid #eee; font-size: 13px; color: #222; }
    .totrow td { border: none; padding-top: 8px; }
    .totrow .tk { text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
    .totrow .tv { text-align: right; font-size: 13px; font-weight: bold; }
    .grand td { border-top: 2px solid #111; padding-top: 12px; }
    .grand .tk { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #111; }
    .grand .tv { font-size: 20px; font-weight: bold; color: #b8942e; }
    .paid { display: inline-block; border: 2px solid #1a9d5a; color: #1a9d5a; font-weight: bold; text-transform: uppercase;
            letter-spacing: 2px; font-size: 12px; padding: 5px 14px; border-radius: 4px; }
    .foot { margin-top: 40px; border-top: 1px solid #eee; padding-top: 14px; font-size: 10px; color: #999; text-align: center; letter-spacing: 0.5px; }
</style>
</head>
<body>
<div class="sheet">

    <table class="top">
        <tr>
            <td>
                <div class="brand">Urban<span>Blade</span></div>
                <div class="brand-sub">Elite Grooming Studio</div>
            </td>
            <td class="doc">
                <div class="h">Recibo</div>
                <div class="m">Folio: {{ $folio }}</div>
                <div class="m">Fecha: {{ $emitido }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Cliente</div>
                <div class="val">{{ $cliente }}</div>
            </td>
            <td>
                <div class="label">Emisor</div>
                <div class="val">UrbanBlade<small>Av. Reforma 123, CDMX</small><small>hola@urbanblade.com · +52 55 1234 5678</small></div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Concepto</th><th class="r">Importe</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $servicio }}{{ $fecha ? ' · '.$fecha : '' }}
                    {!! $barbero ? '<br><span style="color:#888;font-size:11px;">Atendido por '.e($barbero).'</span>' : '' !!}
                </td>
                <td class="r">${{ number_format($monto, 2) }}</td>
            </tr>
            @if($propina > 0)
            <tr>
                <td>Propina</td>
                <td class="r">${{ number_format($propina, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="items" style="margin-top:0;">
        <tr class="totrow"><td class="tk">Subtotal</td><td class="tv">${{ number_format($monto, 2) }}</td></tr>
        <tr class="totrow grand"><td class="tk">Total</td><td class="tv">${{ number_format($monto + $propina, 2) }}</td></tr>
    </table>

    <table style="width:100%; margin-top:22px;">
        <tr>
            <td><span class="paid">Pagado</span></td>
            <td style="text-align:right;">
                <div class="label">Metodo de pago</div>
                <div class="val" style="font-size:12px;">{{ $metodo }}</div>
            </td>
        </tr>
    </table>

    <div class="foot">Gracias por tu visita. Este recibo fue generado electronicamente por UrbanBlade.</div>

</div>
</body>
</html>
