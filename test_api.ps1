# Script de Pruebas de API - Moodle y Cursos
# Ejecutar con: .\test_api.ps1

# Configuración
$BASE_URL = "http://localhost:8000/api"
$TOKEN = "TU_TOKEN_SANCTUM_AQUI"  # Reemplazar con tu token

# Headers
$headers = @{
    "Authorization" = "Bearer $TOKEN"
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PRUEBAS DE API - MOODLE Y CURSOS" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Función para hacer requests
function Test-Endpoint {
    param(
        [string]$Method,
        [string]$Endpoint,
        [string]$Description,
        [object]$Body = $null
    )

    Write-Host "📡 $Description" -ForegroundColor Yellow
    Write-Host "   $Method $Endpoint" -ForegroundColor Gray

    try {
        $url = "$BASE_URL$Endpoint"

        if ($Body) {
            $jsonBody = $Body | ConvertTo-Json -Depth 10
            $response = Invoke-RestMethod -Uri $url -Method $Method -Headers $headers -Body $jsonBody
        } else {
            $response = Invoke-RestMethod -Uri $url -Method $Method -Headers $headers
        }

        Write-Host "   ✅ SUCCESS" -ForegroundColor Green
        Write-Host "   Response: $($response | ConvertTo-Json -Depth 2 -Compress)" -ForegroundColor Gray
        Write-Host ""
        return $response
    }
    catch {
        Write-Host "   ❌ ERROR: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host ""
        return $null
    }
}

# 1. Verificar conexión con Moodle
Write-Host "`n=== PRUEBAS DE MOODLE ===" -ForegroundColor Magenta
Test-Endpoint -Method "GET" -Endpoint "/moodle/test/connection" -Description "Verificar conexión con Moodle"

# 2. Listar funciones de Moodle
Test-Endpoint -Method "GET" -Endpoint "/moodle/test/functions" -Description "Listar funciones disponibles de Moodle"

# 3. Listar categorías de Moodle
$categories = Test-Endpoint -Method "GET" -Endpoint "/moodle/test/categories" -Description "Listar categorías de Moodle"

# 4. Listar cursos de Moodle
Test-Endpoint -Method "GET" -Endpoint "/moodle/test/courses" -Description "Listar cursos de Moodle"

# 5. Crear curso de prueba en Moodle (opcional - comentado por defecto)
<#
if ($categories -and $categories.data.Count -gt 0) {
    $firstCategoryId = $categories.data[0].id
    $testCourse = @{
        fullname = "Curso API Test $(Get-Date -Format 'yyyyMMdd_HHmmss')"
        shortname = "TEST_API_$(Get-Random -Maximum 9999)"
        categoryid = $firstCategoryId
        summary = "Curso creado mediante API de prueba"
        startdate = [int](Get-Date -UFormat %s)
        enddate = [int](Get-Date).AddMonths(4).ToUniversalTime().Subtract([datetime]::UnixEpoch).TotalSeconds
    }

    $created = Test-Endpoint -Method "POST" -Endpoint "/moodle/test/courses" -Description "Crear curso de prueba en Moodle" -Body $testCourse

    # Eliminar curso de prueba
    if ($created -and $created.data -and $created.data.id) {
        Start-Sleep -Seconds 2
        Test-Endpoint -Method "DELETE" -Endpoint "/moodle/test/courses/$($created.data.id)" -Description "Eliminar curso de prueba de Moodle"
    }
}
#>

# 6. Pruebas de Cursos del CRM
Write-Host "`n=== PRUEBAS DE CURSOS CRM ===" -ForegroundColor Magenta

# Listar cursos del CRM
$courses = Test-Endpoint -Method "GET" -Endpoint "/courses?per_page=5" -Description "Listar primeros 5 cursos del CRM"

# Obtener detalles de un curso específico
if ($courses -and $courses.data.Count -gt 0) {
    $firstCourseId = $courses.data[0].id
    Test-Endpoint -Method "GET" -Endpoint "/courses/$firstCourseId" -Description "Obtener detalles del curso ID: $firstCourseId"
}

# Listar cursos disponibles para estudiantes
Test-Endpoint -Method "GET" -Endpoint "/courses/available-for-students?prospecto_ids[]=1" -Description "Cursos disponibles para prospecto ID: 1"

# Obtener cursos por programas
$programBody = @{
    program_ids = @(1, 2)
}
Test-Endpoint -Method "POST" -Endpoint "/courses/by-programs" -Description "Cursos de programas 1 y 2" -Body $programBody

# 7. Pruebas de Consultas Moodle
Write-Host "`n=== PRUEBAS DE CONSULTAS MOODLE ===" -ForegroundColor Magenta
Test-Endpoint -Method "GET" -Endpoint "/moodle/consultas/1234567" -Description "Cursos por carnet 1234567"
Test-Endpoint -Method "GET" -Endpoint "/moodle/programacion-cursos" -Description "Programación de cursos"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "PRUEBAS COMPLETADAS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "`nNotas:" -ForegroundColor Yellow
Write-Host "- Si ves errores 401, el token de autenticación no es válido" -ForegroundColor Gray
Write-Host "- Si ves errores 404, las rutas no están registradas" -ForegroundColor Gray
Write-Host "- Si ves errores 500, revisar storage/logs/laravel.log" -ForegroundColor Gray
Write-Host "`nPara obtener un token válido:" -ForegroundColor Yellow
Write-Host "1. Hacer login: POST /api/login" -ForegroundColor Gray
Write-Host "2. Copiar el token de la respuesta" -ForegroundColor Gray
Write-Host "3. Reemplazar TU_TOKEN_SANCTUM_AQUI en este script" -ForegroundColor Gray
