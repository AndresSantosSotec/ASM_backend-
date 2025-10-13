<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Estudiantes Matriculados</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2196F3;
        }
        .header h1 {
            margin: 0;
            color: #2196F3;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin: 20px 0;
        }
        .section-title {
            background-color: #2196F3;
            color: white;
            padding: 8px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .metric-box {
            display: inline-block;
            width: 30%;
            padding: 10px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
        }
        .metric-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .stats-container {
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Listado de Estudiantes Matriculados</h1>
        <p>Generado el: {{ $fecha }}</p>
        @if(isset($datos->filtros))
        <p>Período: {{ $datos->filtros->fechaInicio ?? 'N/A' }} - {{ $datos->filtros->fechaFin ?? 'N/A' }}</p>
        @endif
    </div>

    @if(isset($datos->estadisticas))
    <div class="section">
        <div class="section-title">Estadísticas Generales</div>
        <div class="stats-container">
            <div class="metric-box">
                <div class="metric-value">{{ $datos->estadisticas->totalEstudiantes ?? 0 }}</div>
                <div class="metric-label">Total Estudiantes</div>
            </div>
            <div class="metric-box">
                <div class="metric-value">{{ $datos->estadisticas->nuevos ?? 0 }}</div>
                <div class="metric-label">Nuevos</div>
            </div>
            <div class="metric-box">
                <div class="metric-value">{{ $datos->estadisticas->recurrentes ?? 0 }}</div>
                <div class="metric-label">Recurrentes</div>
            </div>
        </div>
    </div>

    @if(isset($datos->estadisticas->distribucionProgramas) && count($datos->estadisticas->distribucionProgramas) > 0)
    <div class="section">
        <div class="section-title">Distribución por Programas</div>
        <table>
            <thead>
                <tr>
                    <th>Programa</th>
                    <th>Total</th>
                    <th>Porcentaje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos->estadisticas->distribucionProgramas as $programa)
                <tr>
                    <td>{{ $programa->programa ?? 'N/A' }}</td>
                    <td>{{ $programa->total ?? 0 }}</td>
                    <td>{{ number_format($programa->porcentaje ?? 0, 2) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endif

    @if(isset($datos->estudiantes) && count($datos->estudiantes) > 0)
    <div class="section">
        <div class="section-title">Listado de Estudiantes</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Carnet</th>
                    <th>Fecha Matrícula</th>
                    <th>Tipo</th>
                    <th>Programa</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos->estudiantes as $estudiante)
                <tr>
                    <td>{{ $estudiante->id ?? '' }}</td>
                    <td>{{ $estudiante->nombre ?? '' }}</td>
                    <td>{{ $estudiante->carnet ?? 'N/A' }}</td>
                    <td>{{ $estudiante->fechaMatricula ?? '' }}</td>
                    <td>{{ $estudiante->tipo ?? '' }}</td>
                    <td>{{ $estudiante->programa ?? '' }}</td>
                    <td>{{ $estudiante->estado ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <p style="margin-top: 10px; font-size: 10px;">
            Total de registros: {{ $datos->total ?? count($datos->estudiantes) }}
        </p>
    </div>
    @endif

    <div class="footer">
        <p>Sistema de Gestión Académica - Reporte generado automáticamente</p>
    </div>
</body>
</html>
