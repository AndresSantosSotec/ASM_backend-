<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ranking de Estudiantes</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
    </style>
</head>
<body>
    <h1>Ranking de Estudiantes</h1>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Programa</th>
                <th>GPA</th>
                <th>Créditos</th>
                <th>Progreso %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $student)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $student->nombre_completo }}</td>
                <td>{{ $student->nombre_del_programa }}</td>
                <td>{{ number_format($student->gpa_actual, 2) }}</td>
                <td>{{ $student->credits }}</td>
                <td>{{ number_format($student->progreso, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
