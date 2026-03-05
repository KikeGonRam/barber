<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Pago</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #666; }
        .box { border: 1px solid #ddd; padding: 12px; margin-top: 12px; }
        .row { margin: 4px 0; }
    </style>
</head>
<body>
    <h1>Comprobante de Pago</h1>
    <div class="muted">Folio: PAGO-{{ $payment->created_at?->format('YmdHis') }}</div>
    <div class="muted">Fecha: {{ $payment->created_at?->format('d/m/Y H:i') }}</div>

    <div class="box">
        <div class="row"><strong>Cliente:</strong> {{ $payment->appointment?->client?->user?->name }}</div>
        <div class="row"><strong>Barbero:</strong> {{ $payment->appointment?->barber?->user?->name }}</div>
        <div class="row"><strong>Servicio:</strong> {{ $payment->appointment?->service?->nombre }}</div>
        <div class="row"><strong>Cita:</strong> {{ $payment->appointment?->fecha }} {{ $payment->appointment?->hora_inicio }} - {{ $payment->appointment?->hora_fin }}</div>
    </div>

    <div class="box">
        <div class="row"><strong>Método de pago:</strong> {{ ucfirst($payment->metodo_pago) }}</div>
        <div class="row"><strong>Monto:</strong> ${{ number_format((float) $payment->monto, 2) }}</div>
        <div class="row"><strong>Propina:</strong> ${{ number_format((float) $payment->propina, 2) }}</div>
        <div class="row"><strong>Total:</strong> ${{ number_format((float) $payment->monto + (float) $payment->propina, 2) }}</div>
    </div>

    <div class="muted" style="margin-top: 16px;">Generado por {{ $payment->creator?->name ?? 'Sistema' }}</div>
</body>
</html>
