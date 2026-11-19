@php
    // Todos los montos son pesos mexicanos: se muestran completos y con "$", nunca abreviados (12,000, no 12k).
    $moneyKeys = ['monto', 'propina', 'total', 'precio_cobrado', 'precio_venta', 'gasto_total'];
    $cell = function ($key, $value) use ($moneyKeys) {
        if ($value === null || $value === '') {
            return '—';
        }
        if (in_array($key, $moneyKeys, true) && is_numeric($value)) {
            return '$'.number_format((float) $value, 2);
        }

        return is_numeric($value) && ! is_string($value) ? number_format($value) : $value;
    };
    $isNumeric = fn ($key) => in_array($key, array_merge($moneyKeys, ['visitas', 'completadas', 'stock_actual', 'stock_minimo', 'movimientos']), true);

    $palette = ['#d4af37', '#2a9d8f', '#4c78c9', '#8e6bbf', '#e07a3f', '#5aa469', '#c8556d', '#7a8793'];
    $chartLabels = $chart['labels'] ?? [];
    $chartValues = $chart['values'] ?? [];
    $chartMax = max(1, empty($chartValues) ? 1 : max($chartValues));
    $chartIsCurrency = ($chart['unit'] ?? null) === 'MXN';
    $chartValueFmt = fn ($v) => $chartIsCurrency ? '$'.number_format($v ?? 0) : number_format($v ?? 0);
    $isTimeSeries = ! empty($chartLabels) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $chartLabels[0]);
    $chartLabelFmt = fn ($label) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $label)
        ? \Carbon\Carbon::parse($label)->translatedFormat('d M')
        : \Illuminate\Support\Str::limit($label, 24);
    if ($isTimeSeries && count($chartLabels) > 45) {
        $chartLabels = array_slice($chartLabels, -45);
        $chartValues = array_slice($chartValues, -45);
    }
    $generated = now();
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 78px 44px 64px 44px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #2b2b2b; line-height: 1.5; margin: 0; padding: 0; }

        /* ---------- Encabezado del reporte (compacto, en la primera página) ---------- */
        .cover { border-top: 6px solid #d4af37; padding: 18px 0 16px; margin-bottom: 18px; border-bottom: 1px solid #e6e1cf; }
        .cover table { width: 100%; border-collapse: collapse; }
        .cover td { vertical-align: bottom; }
        .brand { font-size: 22px; font-weight: bold; color: #0a0a0a; letter-spacing: -0.5px; margin: 0; }
        .brand span { color: #b8941f; }
        .brand-sub { font-size: 7px; letter-spacing: 3px; color: #8a8a8a; text-transform: uppercase; margin: 2px 0 0; }
        .cover-kind { font-size: 8px; letter-spacing: 3px; color: #b8941f; text-transform: uppercase; font-weight: bold; margin: 0; }
        .cover-title { font-size: 24px; line-height: 1.1; font-weight: bold; color: #0a0a0a; margin: 4px 0 0; letter-spacing: -0.5px; }
        .cover-period { font-size: 11px; color: #555555; margin: 6px 0 0; }
        .cover-chips { margin-top: 12px; }
        .cover-chips span { display: inline-block; background-color: #f7f4e8; border: 1px solid #e6e1cf; padding: 3px 9px; margin-right: 6px; font-size: 8px; color: #5c4a0f; }
        .cover-chips b { color: #0a0a0a; }

        /* ---------- Secciones ---------- */
        .section { page-break-before: auto; margin-bottom: 8px; }
        .section.first, .section.flow { page-break-before: auto; }
        .eyebrow { font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #b8941f; font-weight: bold; margin: 0 0 4px; }
        h2.section-title { font-size: 20px; margin: 0 0 14px; color: #0a0a0a; letter-spacing: -0.5px; }
        h3.sub-title { font-size: 11px; margin: 22px 0 8px; text-transform: uppercase; letter-spacing: 1.5px; color: #0a0a0a; border-left: 4px solid #d4af37; padding-left: 8px; }
        p.lead { font-size: 12px; color: #444444; margin: 0 0 6px; }

        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 7px 10px; border-bottom: 1px solid #ececec; font-size: 10px; }
        table.kv td.k { width: 30%; color: #777777; text-transform: uppercase; letter-spacing: 1px; font-size: 8px; font-weight: bold; }

        table.toc { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.toc td { padding: 6px 0; border-bottom: 1px dotted #cccccc; font-size: 10px; color: #444444; }
        table.toc td.n { width: 28px; color: #d4af37; font-weight: bold; }

        /* ---------- Resumen ejecutivo ---------- */
        table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
        table.kpis td { width: 25%; background-color: #faf8f0; border: 1px solid #e6e1cf; padding: 12px 12px; vertical-align: top; border-top: 3px solid #d4af37; }
        .kpi-label { font-size: 7px; letter-spacing: 2px; text-transform: uppercase; color: #777777; margin: 0 0 6px; }
        .kpi-value { font-size: 17px; font-weight: bold; color: #0a0a0a; margin: 0; }
        .kpi-hint { font-size: 8px; color: #8a6d12; margin: 4px 0 0; }

        table.findings { width: 100%; border-collapse: collapse; }
        table.findings td { padding: 9px 0; border-bottom: 1px solid #ececec; font-size: 11px; vertical-align: top; }
        table.findings td.n { width: 26px; font-weight: bold; color: #d4af37; font-size: 13px; }

        /* ---------- Graficas ---------- */
        .chart-box { background-color: #fdfcf8; border: 1px solid #e6e1cf; padding: 16px 20px; }
        .chart-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #8a6d12; margin: 0 0 2px; }
        .chart-subtitle { font-size: 8px; color: #777777; margin: 0 0 14px; }
        .chart-row { margin-bottom: 8px; white-space: nowrap; }
        .chart-label { display: inline-block; width: 130px; font-size: 9px; color: #333333; vertical-align: middle; overflow: hidden; }
        .chart-track { display: inline-block; width: 270px; height: 12px; background-color: #efece0; vertical-align: middle; }
        .chart-bar { display: block; height: 12px; }
        .chart-value { display: inline-block; width: 90px; text-align: right; font-size: 9px; font-weight: bold; color: #0a0a0a; vertical-align: middle; }
        table.cols { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.cols td { vertical-align: bottom; text-align: center; padding: 0 1px; }
        table.cols td.lbl { font-size: 6px; color: #666666; padding-top: 4px; vertical-align: top; }
        table.cols .col-bar { background-color: #d4af37; margin: 0 auto; width: 100%; }
        .chart-note { font-size: 8px; color: #777777; margin: 10px 0 0; }

        /* ---------- Tabla de detalle ---------- */
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.8px; color: #5c4a0f; background-color: #f1ecd8; border-bottom: 2px solid #d4af37; padding: 9px 7px; }
        table.data td { padding: 7px; border-bottom: 1px solid #ececec; font-size: 9px; color: #333333; }
        table.data tr.alt td { background-color: #f7f7f5; }
        table.data .r { text-align: right; }
        .note { background-color: #faf6e6; border-left: 4px solid #d4af37; padding: 10px 14px; font-size: 9px; color: #6b5a14; margin-top: 14px; }

        ul.notes { margin: 0; padding-left: 16px; }
        ul.notes li { margin-bottom: 6px; font-size: 10px; color: #444444; }
    </style>
</head>
<body>
    {{-- ============ PORTADA ============ --}}
    <div class="cover">
        <table role="presentation">
            <tr>
                <td>
                    <p class="cover-kind">Reporte ejecutivo</p>
                    <p class="cover-title">{{ $title }}</p>
                    <p class="cover-period">{{ $narrative['period'] }}</p>
                </td>
                <td style="text-align: right; width: 150px;">
                    <p class="brand">Urban<span>Blade</span></p>
                    <p class="brand-sub">Elite Grooming Studio</p>
                </td>
            </tr>
        </table>
        <div class="cover-chips">
            <span>Generado <b>{{ $generated->translatedFormat('d \d\e F \d\e Y') }}</b></span>
            <span>Folio <b>{{ $folio }}</b></span>
            <span>Preparado por <b>{{ $preparedBy ?? 'Administración UrbanBlade' }}</b></span>
        </div>
    </div>

    {{-- ============ INTRODUCCION ============ --}}
    <div class="section first">
        <p class="eyebrow">01 · Introducción</p>
        <h2 class="section-title">Acerca de este reporte</h2>
        <p class="lead">{{ $narrative['purpose'] }}</p>
        <p class="lead">Las cifras se calculan a partir de los registros de UrbanBlade al momento de la generación y se actualizan cada vez que se exporta el documento.</p>

        <h3 class="sub-title">Alcance</h3>
        <table class="kv" role="presentation">
            <tr><td class="k">Periodo</td><td>{{ $narrative['period'] }}</td></tr>
            @foreach($narrative['scope'] as [$label, $value])
                <tr><td class="k">{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
            <tr><td class="k">Registros analizados</td><td>{{ number_format($total) }}</td></tr>
            <tr><td class="k">Generado</td><td>{{ $generated->format('d/m/Y H:i') }}</td></tr>
        </table>

    </div>

    {{-- ============ RESUMEN EJECUTIVO ============ --}}
    <div class="section">
        <p class="eyebrow">02 · Resumen ejecutivo</p>
        <h2 class="section-title">Indicadores clave</h2>
        @if(! empty($narrative['kpis']))
            <table class="kpis" role="presentation">
                <tr>
                    @foreach($narrative['kpis'] as $kpi)
                        <td>
                            <p class="kpi-label">{{ $kpi['label'] }}</p>
                            <p class="kpi-value">{{ $kpi['value'] }}</p>
                            <p class="kpi-hint">{{ $kpi['hint'] }}</p>
                        </td>
                    @endforeach
                </tr>
            </table>
        @else
            <p class="lead">No hay registros en el periodo seleccionado.</p>
        @endif

        <p class="eyebrow" style="margin-top: 30px;">03 · Análisis</p>
        <h2 class="section-title">Hallazgos principales</h2>
        <table class="findings" role="presentation">
            @foreach($narrative['findings'] as $i => $finding)
                <tr><td class="n">{{ $i + 1 }}</td><td>{{ $finding }}</td></tr>
            @endforeach
        </table>

        @if(! empty($chartLabels))
            <h3 class="sub-title">{{ $chart['title'] ?? 'Gráfica' }}</h3>
            <div class="chart-box">
                <p class="chart-title">{{ $chart['title'] ?? 'Gráfica' }}</p>
                @if(! empty($chart['x_label']) || ! empty($chart['y_label']))
                    <p class="chart-subtitle">{{ $chart['x_label'] ?? '' }}{{ ! empty($chart['x_label']) && ! empty($chart['y_label']) ? ' vs. ' : '' }}{{ $chart['y_label'] ?? '' }}</p>
                @endif

                @if($isTimeSeries)
                    <table class="cols" role="presentation">
                        <tr>
                            @foreach($chartValues as $v)
                                <td><div class="col-bar" style="height: {{ max(2, round(($v / $chartMax) * 120)) }}px;"></div></td>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($chartLabels as $i => $label)
                                <td class="lbl">{{ count($chartLabels) <= 16 || $i % 3 === 0 ? \Carbon\Carbon::parse($label)->format('d/m') : '' }}</td>
                            @endforeach
                        </tr>
                    </table>
                    <p class="chart-note">Máximo del periodo: {{ $chartValueFmt($chartMax) }}{{ $chartIsCurrency ? ' MXN' : '' }}</p>
                @else
                    @foreach($chartLabels as $i => $label)
                        <div class="chart-row">
                            <span class="chart-label">{{ $chartLabelFmt($label) }}</span><span class="chart-track"><span class="chart-bar" style="width: {{ round((($chartValues[$i] ?? 0) / $chartMax) * 100) }}%; background-color: {{ $palette[$i % count($palette)] }};"></span></span><span class="chart-value">{{ $chartValueFmt($chartValues[$i] ?? 0) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        @endif
    </div>

    {{-- ============ DETALLE ============ --}}
    <div class="section">
        <p class="eyebrow">04 · Detalle</p>
        <h2 class="section-title">Detalle del informe</h2>

        <table class="data">
            <thead>
                <tr>
                    @foreach($headings as $i => $heading)
                        <th class="{{ $isNumeric($keys[$i] ?? '') ? 'r' : '' }}">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $n => $row)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        @foreach($keys as $key)
                            <td class="{{ $isNumeric($key) ? 'r' : '' }}">{{ $cell($key, $row[$key] ?? null) }}</td>
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

        @if(! empty($trimmed) && $trimmed)
            <div class="note">
                Este PDF muestra los primeros {{ number_format($pdf_limit) }} registros de {{ number_format($total) }} totales.
                Para exportar todos los datos usa el formato Excel o CSV.
            </div>
        @endif
    </div>

    {{-- ============ NOTAS ============ --}}
    <div class="section flow">
        <p class="eyebrow" style="margin-top: 28px;">05 · Notas</p>
        <h2 class="section-title">Notas y metodología</h2>
        <ul class="notes">
            <li>Los montos están expresados en pesos mexicanos (MXN) e incluyen las propinas cuando el reporte las considera.</li>
            <li>Las fechas y horas corresponden a la zona horaria de la barbería; el periodo incluye el día inicial y el final.</li>
            <li>Los ingresos por servicios provienen de los cobros registrados en las citas; los de productos, de los pedidos marcados como entregados.</li>
            <li>Este documento es un reporte interno de gestión. No sustituye una factura fiscal (CFDI) ni un estado contable.</li>
            <li>Para trabajar con los datos completos, exporta este mismo reporte en Excel: incluye el detalle sin recorte y los indicadores.</li>
        </ul>

        <h3 class="sub-title">Control del documento</h3>
        <table class="kv" role="presentation">
            <tr><td class="k">Folio</td><td>{{ $folio }}</td></tr>
            <tr><td class="k">Generado</td><td>{{ $generated->format('d/m/Y H:i') }}</td></tr>
            <tr><td class="k">Preparado por</td><td>{{ $preparedBy ?? 'Administración UrbanBlade' }}</td></tr>
            <tr><td class="k">Clasificación</td><td>Confidencial · Uso interno</td></tr>
        </table>
    </div>
</body>
</html>
