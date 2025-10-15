# 📊 Dashboard Administrativo - Guía Completa

## 🎯 Descripción

El **Dashboard Administrativo** es un endpoint completo que proporciona métricas, estadísticas y análisis en tiempo real del sistema académico. Diseñado para el módulo de administración, ofrece una vista integral de matrículas, estudiantes, cursos y graduaciones.

## 📚 Índice de Documentación

1. **[README.md](README.md)** - Este archivo (vista general)
2. **[DASHBOARD_ADMINISTRATIVO_API.md](DASHBOARD_ADMINISTRATIVO_API.md)** - Referencia completa de la API
3. **[DASHBOARD_ADMINISTRATIVO_RESUMEN.md](DASHBOARD_ADMINISTRATIVO_RESUMEN.md)** - Resumen ejecutivo en español
4. **[DASHBOARD_TESTING_GUIDE.md](DASHBOARD_TESTING_GUIDE.md)** - Guía de pruebas con ejemplos
5. **[DASHBOARD_ESTRUCTURA_VISUAL.md](DASHBOARD_ESTRUCTURA_VISUAL.md)** - Diagramas visuales y estructura

## 🚀 Inicio Rápido

### Endpoint Principal
```bash
GET /api/administracion/dashboard
```

### Ejemplo de Uso
```bash
curl -X GET \
  'http://localhost:8000/api/administracion/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

### Respuesta Esperada
```json
{
  "matriculas": { "total": 245, "porcentajeCambio": 11.87 },
  "alumnosNuevos": { "total": 87, "porcentajeCambio": 4.82 },
  "proximosInicios": { "total": 12 },
  "graduaciones": { "total": 34 },
  "evolucionMatricula": [...],
  "distribucionProgramas": [...],
  "notificaciones": {...},
  "estadisticas": {...}
}
```

## 📈 Métricas Incluidas

### 1️⃣ Matrículas del Mes
- Total de matrículas en el mes actual
- Comparación con mes anterior
- Porcentaje de cambio

### 2️⃣ Alumnos Nuevos
- Prospectos convertidos a estudiantes
- Comparación mensual
- Tasa de conversión

### 3️⃣ Próximos Inicios
- Cursos que inician en 30 días
- Periodos de inscripción próximos
- Total consolidado

### 4️⃣ Graduaciones
- Estudiantes graduándose en próximo trimestre
- Proyecciones de finalización
- Fechas de graduación

### 5️⃣ Evolución de Matrícula
- Datos históricos mensuales
- Periodos: 6 meses, 1 año, todo
- Listo para gráficas

### 6️⃣ Distribución por Programas
- Estudiantes por programa académico
- Ordenado por popularidad
- Totales y porcentajes

### 7️⃣ Notificaciones Importantes
- **Solicitudes Pendientes**: Prospectos en revisión
- **Graduaciones Próximas**: Estudiantes finalizando
- **Cursos por Finalizar**: Cursos próximos a terminar

### 8️⃣ Estadísticas Generales
- Total de estudiantes activos
- Total de programas disponibles
- Total de cursos en sistema
- **Análisis especial**: Estudiantes en múltiples programas
  - Total de estudiantes multi-programa
  - Promedio de programas por estudiante
  - Máximo de programas
  - Top 5 estudiantes con más programas

## 🔧 Configuración

### Requisitos
- Laravel 9+
- PHP 8.0+
- MySQL/PostgreSQL
- Sanctum para autenticación

### Instalación
El endpoint ya está implementado. Solo necesitas:

1. Asegurarte que las migraciones estén ejecutadas
2. Configurar autenticación Sanctum
3. Tener datos en la base de datos

### Autenticación
Todas las rutas están protegidas con `auth:sanctum`:

```php
Route::prefix('administracion')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [AdministracionController::class, 'dashboard']);
    Route::get('/dashboard/exportar', [AdministracionController::class, 'exportar']);
});
```

## 💻 Ejemplos de Integración

### JavaScript/Fetch
```javascript
const token = localStorage.getItem('token');

fetch('/api/administracion/dashboard', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => {
  console.log('Matrículas:', data.matriculas);
  console.log('Alumnos Nuevos:', data.alumnosNuevos);
});
```

### React Hook
```javascript
import { useState, useEffect } from 'react';

function useDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/administracion/dashboard', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    })
    .then(res => res.json())
    .then(data => {
      setData(data);
      setLoading(false);
    });
  }, []);

  return { data, loading };
}
```

### Vue Composition API
```javascript
import { ref, onMounted } from 'vue';

export function useDashboard() {
  const data = ref(null);
  const loading = ref(true);

  onMounted(async () => {
    const response = await fetch('/api/administracion/dashboard', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    data.value = await response.json();
    loading.value = false;
  });

  return { data, loading };
}
```

### Angular Service
```typescript
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class DashboardService {
  constructor(private http: HttpClient) {}

  getDashboard(): Observable<any> {
    return this.http.get('/api/administracion/dashboard');
  }
}
```

## 📊 Visualización de Datos

### Gráfica de Evolución
```javascript
// Ejemplo con Chart.js
const evolucionData = {
  labels: data.evolucionMatricula.map(item => item.mes),
  datasets: [{
    label: 'Matrículas',
    data: data.evolucionMatricula.map(item => item.total),
    borderColor: 'rgb(75, 192, 192)',
    tension: 0.1
  }]
};
```

### Gráfica de Distribución
```javascript
// Ejemplo con Chart.js
const distribucionData = {
  labels: data.distribucionProgramas.map(p => p.abreviatura),
  datasets: [{
    label: 'Estudiantes por Programa',
    data: data.distribucionProgramas.map(p => p.totalEstudiantes),
    backgroundColor: [
      'rgba(255, 99, 132, 0.5)',
      'rgba(54, 162, 235, 0.5)',
      'rgba(255, 206, 86, 0.5)',
      // ... más colores
    ]
  }]
};
```

## 🧪 Testing

### Validar Estructura
```bash
# Verificar que el endpoint responde
curl -I http://localhost:8000/api/administracion/dashboard \
  -H "Authorization: Bearer $TOKEN"

# Verificar estructura JSON
curl http://localhost:8000/api/administracion/dashboard \
  -H "Authorization: Bearer $TOKEN" | jq 'keys'
```

### Pruebas con Postman
Importa la colección de Postman incluida en la documentación o crea tests:

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Has all required keys", function () {
    var data = pm.response.json();
    pm.expect(data).to.have.all.keys(
        'matriculas', 
        'alumnosNuevos', 
        'proximosInicios',
        'graduaciones',
        'evolucionMatricula',
        'distribucionProgramas',
        'notificaciones',
        'estadisticas'
    );
});
```

## 🔍 Solución de Problemas

### Error 401 Unauthorized
**Problema**: Token inválido o expirado  
**Solución**: Generar nuevo token de autenticación

### Error 500 Internal Server Error
**Problema**: Error en base de datos o lógica  
**Solución**: Revisar logs en `storage/logs/laravel.log`

### Datos Vacíos
**Problema**: Base de datos sin registros  
**Solución**: Poblar con datos de prueba o verificar migraciones

### Lentitud en Respuesta
**Problema**: Consultas no optimizadas  
**Solución**: Implementar caché (Redis recomendado)

```php
// Ejemplo de caché
public function dashboard(Request $request) {
    return Cache::remember('dashboard', 300, function() {
        // ... lógica del dashboard
    });
}
```

## 🚀 Optimizaciones Recomendadas

### 1. Implementar Caché
```php
// En el controller
$cacheKey = 'dashboard_' . auth()->id();
return Cache::remember($cacheKey, 300, function() {
    // Lógica del dashboard
});
```

### 2. Índices de Base de Datos
```sql
-- Índices recomendados
CREATE INDEX idx_ep_created_at ON estudiante_programa(created_at);
CREATE INDEX idx_course_start_date ON courses(start_date);
CREATE INDEX idx_course_end_date ON courses(end_date);
CREATE INDEX idx_ep_fecha_fin ON estudiante_programa(fecha_fin);
```

### 3. Queue para Exportaciones
```php
// Para exportaciones pesadas
public function exportar(Request $request) {
    ExportDashboardJob::dispatch($request->user());
    return response()->json(['message' => 'Export queued']);
}
```

## 📝 Changelog

### v1.0.0 - Octubre 2025
- ✅ Implementación inicial del dashboard
- ✅ Métricas principales (matrículas, alumnos, inicios, graduaciones)
- ✅ Evolución histórica de matrícula
- ✅ Distribución por programas
- ✅ Sistema de notificaciones
- ✅ Estadísticas de múltiples programas
- ✅ Exportación de datos (JSON)
- ✅ Documentación completa

## 🤝 Contribuir

Para contribuir al dashboard:

1. Crear branch desde `main`
2. Implementar mejoras
3. Agregar tests
4. Actualizar documentación
5. Crear Pull Request

## 📄 Licencia

Este proyecto es parte del sistema ASM_backend-.

## 👥 Equipo

Desarrollado por el equipo de desarrollo ASM.

## 📞 Soporte

Para soporte o preguntas:
- Revisar documentación en `/docs`
- Consultar ejemplos en `/examples`
- Contactar al equipo de desarrollo

---

## 🔗 Enlaces Útiles

- [Documentación API](DASHBOARD_ADMINISTRATIVO_API.md)
- [Guía de Testing](DASHBOARD_TESTING_GUIDE.md)
- [Diagramas Visuales](DASHBOARD_ESTRUCTURA_VISUAL.md)
- [Resumen Ejecutivo](DASHBOARD_ADMINISTRATIVO_RESUMEN.md)

---

**Última actualización**: Octubre 2025  
**Versión**: 1.0.0  
**Estado**: ✅ Producción
