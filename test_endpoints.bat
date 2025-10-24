@echo off
echo ========================================
echo Prueba de Endpoints - Cursos y Usuarios
echo ========================================
echo.

REM Configuración
set BASE_URL=http://localhost:8000/api
set TOKEN=TU_TOKEN_AQUI

echo 1. Probando GET /api/courses...
curl -s -w "\nStatus: %%{http_code}\n" ^
  "%BASE_URL%/courses?per_page=5" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Accept: application/json"

echo.
echo ========================================
echo.

echo 2. Probando GET /api/users/role/2 (Facilitadores)...
curl -s -w "\nStatus: %%{http_code}\n" ^
  "%BASE_URL%/users/role/2" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Accept: application/json"

echo.
echo ========================================
echo Pruebas completadas
echo ========================================
pause
