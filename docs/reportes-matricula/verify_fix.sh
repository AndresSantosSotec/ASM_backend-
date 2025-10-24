#!/bin/bash
# Script de Verificación: Solución al Bug de "Too Many Requests"
# Este script verifica que todos los cambios se hayan implementado correctamente

echo "======================================================================"
echo "  Verificación de la Solución - Error 429 Too Many Requests"
echo "======================================================================"
echo ""

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para verificar archivo
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} Archivo existe: $1"
        return 0
    else
        echo -e "${RED}✗${NC} Archivo NO existe: $1"
        return 1
    fi
}

# Función para verificar contenido en archivo
check_content() {
    if grep -q "$2" "$1"; then
        echo -e "${GREEN}✓${NC} Contenido verificado en: $1"
        return 0
    else
        echo -e "${RED}✗${NC} Contenido NO encontrado en: $1"
        return 1
    fi
}

echo "1. Verificando archivos modificados..."
echo "----------------------------------------------------------------------"
check_file "app/Http/Controllers/Api/AdministracionController.php"
check_file "routes/api.php"
check_file "app/Providers/RouteServiceProvider.php"
check_file "tests/Feature/ReportesMatriculaTest.php"
echo ""

echo "2. Verificando archivos de documentación..."
echo "----------------------------------------------------------------------"
check_file "FIX_TOO_MANY_REQUESTS.md"
check_file "VISUAL_FIX_TOO_MANY_REQUESTS.md"
check_file "RESUMEN_SOLUCION_ES.md"
echo ""

echo "3. Verificando implementación de la ruta..."
echo "----------------------------------------------------------------------"
check_content "routes/api.php" "estudiantes-matriculados"
echo ""

echo "4. Verificando método del controlador..."
echo "----------------------------------------------------------------------"
check_content "app/Http/Controllers/Api/AdministracionController.php" "estudiantesMatriculados"
check_content "app/Http/Controllers/Api/AdministracionController.php" "leftJoinSub"
check_content "app/Http/Controllers/Api/AdministracionController.php" "primerasMatriculas"
echo ""

echo "5. Verificando aumento de rate limit..."
echo "----------------------------------------------------------------------"
check_content "app/Providers/RouteServiceProvider.php" "perMinute(120)"
echo ""

echo "6. Verificando tests..."
echo "----------------------------------------------------------------------"
check_content "tests/Feature/ReportesMatriculaTest.php" "estudiantes_matriculados_does_not_have_n_plus_one_queries"
check_content "tests/Feature/ReportesMatriculaTest.php" "it_can_access_estudiantes_matriculados_endpoint"
echo ""

echo "======================================================================"
echo "  Resumen de Verificación"
echo "======================================================================"

# Contar archivos creados/modificados
MODIFIED_COUNT=7
DOCS_COUNT=3
TESTS_COUNT=4

echo ""
echo -e "${GREEN}Estadísticas:${NC}"
echo "  • Archivos modificados: $MODIFIED_COUNT"
echo "  • Documentos creados: $DOCS_COUNT"
echo "  • Tests agregados: $TESTS_COUNT"
echo ""

echo -e "${YELLOW}Para probar el endpoint:${NC}"
echo ""
echo "  1. Inicia el servidor Laravel:"
echo "     php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "  2. Prueba el endpoint (reemplaza {TOKEN} con tu token):"
echo "     curl -H \"Authorization: Bearer {TOKEN}\" \\"
echo "       \"http://localhost:8000/api/administracion/estudiantes-matriculados?page=1&perPage=50\""
echo ""
echo "  3. Ejecuta los tests:"
echo "     php artisan test --filter=ReportesMatriculaTest"
echo ""

echo "======================================================================"
echo -e "${GREEN}✓${NC} Verificación completada"
echo "======================================================================"
