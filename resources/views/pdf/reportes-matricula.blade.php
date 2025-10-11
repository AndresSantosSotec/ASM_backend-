<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Matrícula y Alumnos Nuevos</title>
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
            border-bottom: 2px solid #4CAF50;
        }
        .header h1 {
            margin: 0;
            color: #4CAF50;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin: 20px 0;
        }
        .section-title {
            background-color: #4CAF50;
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
            color: #4CAF50;
        }
        .metric-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .positive {
            color: #4CAF50;
        }
        .negative {
            color: #f44336;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Matrícula y Alumnos Nuevos</h1>
        <p>Generado el: {{ $fecha }}</p>
        @if(isset($datos->periodoActual->rango))
        <p>Período: {{ $datos->periodoActual->rango->descripcion }}</p>
        @endif
    </div>

    @if($detalle === 'complete' || $detalle === 'summary')
    <div class="section">
        <div class="section-title">Resumen Ejecutivo</div>
        
        @if(isset($datos->comparativa))
        <table>
            <thead>
                <tr>
                    <th>Métrica</th>
                    <th>Período Actual</th>
                    <th>Período Anterior</th>
                    <th>Variación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Matriculados</td>
                    <td>{{ $datos->comparativa->totales->actual ?? 0 }}</td>
                    <td>{{ $datos->comparativa->totales->anterior ?? 0 }}</td>
                    <td class="{{ ($datos->comparativa->totales->variacion ?? 0) >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($datos->comparativa->totales->variacion ?? 0, 2) }}%
                    </td>
                </tr>
                <tr>
                    <td>Alumnos Nuevos</td>
                    <td>{{ $datos->comparativa->nuevos->actual ?? 0 }}</td>
                    <td>{{ $datos->comparativa->nuevos->anterior ?? 0 }}</td>
                    <td class="{{ ($datos->comparativa->nuevos->variacion ?? 0) >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($datos->comparativa->nuevos->variacion ?? 0, 2) }}%
                    </td>
                </tr>
                <tr>
                    <td>Alumnos Recurrentes</td>
                    <td>{{ $datos->comparativa->recurrentes->actual ?? 0 }}</td>
                    <td>{{ $datos->comparativa->recurrentes->anterior ?? 0 }}</td>
                    <td class="{{ ($datos->comparativa->recurrentes->variacion ?? 0) >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($datos->comparativa->recurrentes->variacion ?? 0, 2) }}%
                    </td>
                </tr>
            </tbody>
        </table>
        @endif
    </div>

    @if(isset($datos->periodoActual->distribucionProgramas))
    <div class="section">
        <div class="section-title">Distribución por Programas</div>
        <table>
            <thead>
                <tr>
                    <th>Programa</th>
                    <th>Total de Estudiantes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos->periodoActual->distribucionProgramas as $programa)
                <tr>
                    <td>{{ $programa->programa ?? 'N/A' }}</td>
                    <td>{{ $programa->total ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endif

    @if($detalle === 'complete' || $detalle === 'data')
    @if(isset($datos->listado->alumnos))
    <div class="section">
        <div class="section-title">Listado de Alumnos</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Fecha Matrícula</th>
                    <th>Tipo</th>
                    <th>Programa</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos->listado->alumnos as $alumno)
                <tr>
                    <td>{{ $alumno->id ?? '' }}</td>
                    <td>{{ $alumno->nombre ?? '' }}</td>
                    <td>{{ $alumno->fechaMatricula ?? '' }}</td>
                    <td>{{ $alumno->tipo ?? '' }}</td>
                    <td>{{ $alumno->programa ?? '' }}</td>
                    <td>{{ $alumno->estado ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        @if(isset($datos->listado->paginacion))
        <p style="margin-top: 10px; font-size: 10px;">
            Mostrando {{ count($datos->listado->alumnos) }} de {{ $datos->listado->paginacion->total ?? 0 }} registros totales
        </p>
        @endif
    </div>
    @endif
    @endif

    <div class="footer">
        <p>Sistema de Gestión Académica - Reporte generado automáticamente</p>
    </div>
</body>
</html>
