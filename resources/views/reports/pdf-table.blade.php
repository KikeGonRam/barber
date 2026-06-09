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
                <p style="margin: 0; font-size: 10px; font-weight: bold; color: #666;">
                    Total de registros en este informe: <span style="color: #000;">{{ count($rows) }}</span>
                </p>
            </div>
        @endif
    </div>

    <div class="footer">
        Documento Generado Automáticamente por UrbanBlade &bull; Inteligencia de Negocio
    </div>
</body>
</html>
