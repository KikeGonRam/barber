<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura - {{ $payment->id }}</title>
    <style>
        @page { margin: 0; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 13px; 
            color: #333; 
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #0a0a0a;
            color: #ffffff;
            padding: 40px;
            text-align: left;
        }
        .header-title {
            font-size: 32px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -1px;
            margin: 0;
        }
        .header-title span { color: #d4af37; }
        .invoice-info {
            float: right;
            text-align: right;
            margin-top: -50px;
        }
        .invoice-info p { margin: 2px 0; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #888; }
        .invoice-info span { color: #fff; }

        .content { padding: 40px; }
        
        .section-title {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #d4af37;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .customer-barber {
            width: 100%;
            margin-bottom: 40px;
        }
        .customer-barber td { width: 50%; vertical-align: top; }
        .customer-barber h4 { margin: 0 0 5px 0; font-size: 16px; font-weight: 900; }
        .customer-barber p { margin: 0; color: #666; font-size: 12px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .items-table th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #999;
            padding: 12px 0;
            border-bottom: 2px solid #000;
        }
        .items-table td {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .items-table .price { text-align: right; }

        .totals {
            float: right;
            width: 250px;
        }
        .totals-row {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .totals-row.grand-total {
            border-bottom: none;
            padding-top: 15px;
        }
        .totals-label { float: left; color: #888; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .totals-value { float: right; font-weight: 900; font-size: 14px; }
        .grand-total .totals-label { color: #000; font-size: 12px; }
        .grand-total .totals-value { color: #d4af37; font-size: 24px; }

        .footer {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="header-title">Urban<span>Blade</span></h1>
        <div class="invoice-info">
            <p>Factura No: <span>#{{ strtoupper(mb_substr((string) $payment->id, -8)) }}</span></p>
            <p>Fecha: <span>{{ $payment->created_at?->format('d/m/Y') }}</span></p>
            <p>Hora: <span>{{ $payment->created_at?->format('H:i') }}</span></p>
        </div>
    </div>

    <div class="content">
        <table class="customer-barber">
            <tr>
                <td>
                    <div class="section-title">Cliente</div>
                    <h4>{{ $payment->appointment?->client?->user?->name }}</h4>
                    <p>{{ $payment->appointment?->client?->user?->email }}</p>
                    <p>{{ $payment->appointment?->client?->telefono }}</p>
                </td>
                <td>
                    <div class="section-title">Atendido por</div>
                    <h4>Maestro {{ $payment->appointment?->barber?->user?->name }}</h4>
                    @php
                        $esp = $payment->appointment?->barber?->especialidades;
                        $espText = is_array($esp) ? implode(', ', $esp) : ($esp ?? '');
                    @endphp
                    <p>{{ $espText }}</p>
                </td>
            </tr>
        </table>

        <div class="section-title">Resumen del Servicio</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="price">Cantidad</th>
                    <th class="price">Precio Unitario</th>
                    <th class="price">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $payment->appointment?->service?->nombre }}</strong><br>
                        <span style="font-size: 11px; color: #888;">Servicio Premium de Barbería</span>
                    </td>
                    <td class="price">1</td>
                    <td class="price">${{ number_format((float) $payment->monto, 2) }}</td>
                    <td class="price">${{ number_format((float) $payment->monto, 2) }}</td>
                </tr>
                @foreach($payment->appointment?->productos ?? [] as $prod)
                @php $prodPrecio = (float)($prod['precio'] ?? 0); $prodQty = (int)($prod['cantidad'] ?? 1); @endphp
                <tr>
                    <td>
                        <strong>{{ $prod['nombre'] ?? 'Producto' }}</strong><br>
                        <span style="font-size: 11px; color: #888;">Producto adicional</span>
                    </td>
                    <td class="price">{{ $prodQty }}</td>
                    <td class="price">${{ number_format($prodPrecio, 2) }}</td>
                    <td class="price">${{ number_format($prodPrecio * $prodQty, 2) }}</td>
                </tr>
                @endforeach
                @if($payment->propina > 0)
                <tr>
                    <td>
                        <strong>Gratificación / Propina</strong><br>
                        <span style="font-size: 11px; color: #888;">Reconocimiento a la maestría del barbero</span>
                    </td>
                    <td class="price">1</td>
                    <td class="price">${{ number_format((float) $payment->propina, 2) }}</td>
                    <td class="price">${{ number_format((float) $payment->propina, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal</div>
                <div class="totals-value">${{ number_format((float) $payment->monto, 2) }}</div>
                <div class="clear"></div>
            </div>
            @if($payment->propina > 0)
            <div class="totals-row">
                <div class="totals-label">Propina</div>
                <div class="totals-value">${{ number_format((float) $payment->propina, 2) }}</div>
                <div class="clear"></div>
            </div>
            @endif
            <div class="totals-row">
                <div class="totals-label">Método de Pago</div>
                <div class="totals-value" style="font-size: 10px; color: #666;">{{ strtoupper($payment->metodo_pago) }}</div>
                <div class="clear"></div>
            </div>
            <div class="totals-row grand-total">
                <div class="totals-label">Total Pagado</div>
                <div class="totals-value">${{ number_format((float) $payment->monto + (float) $payment->propina, 2) }}</div>
                <div class="clear"></div>
            </div>
        </div>
        <div class="clear"></div>

        <div style="margin-top: 60px; padding: 20px; background-color: #f9f9f9; border-radius: 10px; font-size: 11px; color: #777;">
            <strong>Nota:</strong> Este documento es un comprobante oficial de pago por los servicios prestados en UrbanBlade. Gracias por elegir nuestra excelencia.
        </div>
    </div>

    <div class="footer">
        UrbanBlade &bull; www.urbanblade.com &bull; +52 123 456 7890
    </div>
</body>
</html>
