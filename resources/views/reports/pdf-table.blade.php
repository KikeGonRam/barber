<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 0; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 11px; 
            color: #333; 
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #0a0a0a;
            color: #ffffff;
            padding: 30px 40px;
            text-align: left;
        }
        .header-title {
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -1px;
            margin: 0;
        }
        .header-title span { color: #d4af37; }
        .report-info {
            float: right;
            text-align: right;
            margin-top: -40px;
        }
        .report-info p { margin: 2px 0; font-size: 9px; font-weight: bold; text-transform: uppercase; color: #888; }
        .report-info span { color: #fff; }

        .content { padding: 30px 40px; }
        
        .section-title {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000;
            margin-bottom: 20px;
            border-left: 4px solid #d4af37;
            padding-left: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .data-table th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #d4af37;
            background-color: #1a1a1a;
            padding: 12px 10px;
            border: none;
        }
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            color: #444;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .footer {
            position: absolute;
            bottom: 30px;
            left: 40px;
            right: 40px;
            border-top: 1px solid #eee;
            padding-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary-box {
            background-color: #fcfcfc;
            border: 1px solid #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .clear { clear: both; }

        .chart-box {
            background-color: #0a0a0a;
            border-radius: 8px;
            padding: 20px 25px;
            margin-bottom: 25px;
        }
        .chart-title {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #d4af37;
            margin: 0 0 2px;
        }
        .chart-subtitle {
            font-size: 8px;
            color: #888;
            margin: 0 0 14px;
        }
        .chart-row { margin-bottom: 8px; }
        .chart-label {
            display: inline-block;
            width: 140px;
            font-size: 9px;
            color: #ccc;
            vertical-align: middle;
            overflow: hidden;
        }
        .chart-track {
            display: inline-block;
            width: 340px;
            height: 12px;
            background-color: #1a1a1a;
            vertical-align: middle;
            border-radius: 3px;
        }
        .chart-bar {
            display: block;
            height: 12px;
            background-color: #d4af37;
            border-radius: 3px;
        }
        .chart-value {
            display: inline-block;
            width: 110px;
            text-align: right;
            font-size: 9px;
            font-weight: bold;
            color: #fff;
            vertical-align: middle;
            padding-left: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="header-title">Urban<span>Blade</span></h1>
        <div class="report-info">
            <p>Reporte: <span>{{ strtoupper($title) }}</span></p>
            <p>Fecha Generación: <span>{{ now()->format('d/m/Y H:i') }}</span></p>
        </div>
    </div>

    <div class="content">
        @if(!empty($chart['labels'] ?? []))
            @php
                $chartMax = max(1, max($chart['values']));
                // Fechas ISO (2026-07-07) se muestran como "07 Jul" en vez del
                // string crudo; cualquier otra etiqueta (nombre de barbero,
                // categoría de producto, cliente) se deja tal cual, solo acortada.
                $chartLabelFmt = function ($label) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $label)) {
                        return \Carbon\Carbon::parse($label)->translatedFormat('d M');
                    }

                    return \Illuminate\Support\Str::limit($label, 22);
                };

                // Los montos son pesos mexicanos: se muestran con "$" y
                // separador de miles completo, nunca abreviados (12,000, no 12k).
                $chartIsCurrency = ($chart['unit'] ?? null) === 'MXN';
                $chartValueFmt = fn ($v) => $chartIsCurrency
                    ? '$'.number_format($v ?? 0).' MXN'
                    : number_format($v ?? 0);
            @endphp
            <div class="chart-box">
                <p class="chart-title">{{ $chart['title'] ?? 'Gráfica' }}</p>
                @if(!empty($chart['x_label']) || !empty($chart['y_label']))
                    <p class="chart-subtitle">
                        {{ $chart['x_label'] ?? '' }}{{ !empty($chart['x_label']) && !empty($chart['y_label']) ? ' vs. ' : '' }}{{ $chart['y_label'] ?? '' }}
                    </p>
                @endif
                @foreach($chart['labels'] as $i => $label)
                    @php $pct = round((($chart['values'][$i] ?? 0) / $chartMax) * 100); @endphp
                    <div class="chart-row">
                        <span class="chart-label">{{ $chartLabelFmt($label) }}</span>
                        <span class="chart-track"><span class="chart-bar" style="width: {{ $pct }}%;"></span></span>
                        <span class="chart-value">{{ $chartValueFmt($chart['values'][$i] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="section-title">Detalle del Informe</div>

        <table class="data-table">
            <thead>
                <tr>
                    @foreach($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($keys as $key)
                            <td>{{ $row[$key] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headings) }}" style="text-align: center; padding: 40px; color: #999;">
                            No se encontraron registros para los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if(count($rows) > 0)
            <div class="summary-box">
                @if(!empty($trimmed) && $trimmed)
                    <p style="margin: 0 0 6px; font-size: 10px; font-weight: bold; color: #b45309;">
                        Este PDF muestra los primeros {{ $pdf_limit }} registros de {{ $total }} totales.
                        Para exportar todos los datos usa el formato Excel o CSV.
                    </p>
                @endif
                <p style="margin: 0; font-size: 10px; font-weight: bold; color: #666;">
                    Registros en este documento: <span style="color: #000;">{{ count($rows) }}</span>
                </p>
            </div>
        @endif
    </div>

    <div class="footer">
        Documento Generado Automáticamente por UrbanBlade &bull; Inteligencia de Negocio
    </div>
</body>
</html>
