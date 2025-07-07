<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Financiero</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
    </style>
</head>
<body>
    <h1>Resumen Financiero</h1>
    <table>
        <thead>
            <tr>
                <th>Total Recaudado</th>
                <th>Deuda Vencida</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($total_recaudado, 2) }}</td>
                <td>{{ number_format($deuda_vencida, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
