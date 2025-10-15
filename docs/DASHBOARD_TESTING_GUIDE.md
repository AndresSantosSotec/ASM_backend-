# Guía Rápida - Testeo del Dashboard Administrativo

## URLs de los Endpoints

### Dashboard Principal
```
GET /api/administracion/dashboard
GET /api/administracion/dashboard?periodo=6meses
GET /api/administracion/dashboard?periodo=1año
GET /api/administracion/dashboard?periodo=todo
```

### Exportar Dashboard
```
GET /api/administracion/dashboard/exportar
GET /api/administracion/dashboard/exportar?formato=json
GET /api/administracion/dashboard/exportar?formato=excel
GET /api/administracion/dashboard/exportar?formato=pdf
```

## Ejemplos con cURL

### 1. Obtener Dashboard Completo

```bash
curl -X GET \
  'http://localhost:8000/api/administracion/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Accept: application/json' \
  | json_pp
```

### 2. Dashboard con Evolución de 1 Año

```bash
curl -X GET \
  'http://localhost:8000/api/administracion/dashboard?periodo=1año' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Accept: application/json' \
  | json_pp
```

### 3. Exportar Dashboard

```bash
curl -X GET \
  'http://localhost:8000/api/administracion/dashboard/exportar?formato=json' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Accept: application/json' \
  -o dashboard_export.json
```

## Ejemplos con Postman

### Configuración General
1. **Method**: GET
2. **URL**: `http://localhost:8000/api/administracion/dashboard`
3. **Headers**:
   - `Authorization`: `Bearer YOUR_TOKEN_HERE`
   - `Accept`: `application/json`

### Test Scripts para Postman

```javascript
// Validar respuesta exitosa
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

// Validar estructura de matriculas
pm.test("Matriculas structure is correct", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.matriculas).to.have.property('total');
    pm.expect(jsonData.matriculas).to.have.property('mesAnterior');
    pm.expect(jsonData.matriculas).to.have.property('porcentajeCambio');
});

// Validar estructura de alumnos nuevos
pm.test("Alumnos nuevos structure is correct", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.alumnosNuevos).to.have.property('total');
    pm.expect(jsonData.alumnosNuevos).to.have.property('mesAnterior');
});

// Validar que evolucion tiene datos
pm.test("Evolucion matricula has data", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.evolucionMatricula).to.be.an('array');
    pm.expect(jsonData.evolucionMatricula.length).to.be.greaterThan(0);
});

// Validar notificaciones
pm.test("Notificaciones structure is correct", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.notificaciones).to.have.property('solicitudesPendientes');
    pm.expect(jsonData.notificaciones).to.have.property('graduacionesProximas');
    pm.expect(jsonData.notificaciones).to.have.property('cursosPorFinalizar');
});
```

## Ejemplos con JavaScript (Fetch API)

### Dashboard Principal

```javascript
async function getDashboard() {
  try {
    const response = await fetch('http://localhost:8000/api/administracion/dashboard', {
      method: 'GET',
      headers: {
        'Authorization': 'Bearer YOUR_TOKEN_HERE',
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    console.log('Dashboard Data:', data);
    return data;
  } catch (error) {
    console.error('Error fetching dashboard:', error);
  }
}

// Usar la función
getDashboard().then(data => {
  console.log('Matriculas:', data.matriculas);
  console.log('Alumnos Nuevos:', data.alumnosNuevos);
  console.log('Estadísticas:', data.estadisticas);
});
```

### Con Axios

```javascript
import axios from 'axios';

const getDashboard = async (periodo = '6meses') => {
  try {
    const response = await axios.get('http://localhost:8000/api/administracion/dashboard', {
      params: { periodo },
      headers: {
        'Authorization': 'Bearer YOUR_TOKEN_HERE',
        'Accept': 'application/json'
      }
    });
    return response.data;
  } catch (error) {
    console.error('Error:', error.response?.data || error.message);
    throw error;
  }
};

// Uso
getDashboard('1año').then(data => {
  console.log('Dashboard data:', data);
});
```

## React Hook Ejemplo

```javascript
import { useState, useEffect } from 'react';
import axios from 'axios';

const useDashboard = (periodo = '6meses') => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchDashboard = async () => {
      try {
        setLoading(true);
        const token = localStorage.getItem('token'); // o desde context
        const response = await axios.get('/api/administracion/dashboard', {
          params: { periodo },
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        setData(response.data);
        setError(null);
      } catch (err) {
        setError(err.response?.data?.message || 'Error al cargar dashboard');
      } finally {
        setLoading(false);
      }
    };

    fetchDashboard();
  }, [periodo]);

  return { data, loading, error };
};

// Uso en componente
function DashboardPage() {
  const { data, loading, error } = useDashboard('1año');

  if (loading) return <div>Cargando dashboard...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      <h1>Dashboard Administrativo</h1>
      <div className="stats">
        <div>Matrículas: {data.matriculas.total}</div>
        <div>Alumnos Nuevos: {data.alumnosNuevos.total}</div>
        <div>Próximos Inicios: {data.proximosInicios.total}</div>
      </div>
    </div>
  );
}
```

## Validación de Datos

### Validar que el endpoint responde

```bash
# Simple ping
curl -I -X GET \
  'http://localhost:8000/api/administracion/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE'
```

### Validar estructura JSON

```bash
# Guardar respuesta y validar con jq
curl -s -X GET \
  'http://localhost:8000/api/administracion/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Accept: application/json' \
  | jq '.matriculas, .alumnosNuevos, .proximosInicios'
```

### Verificar todas las claves principales

```bash
curl -s -X GET \
  'http://localhost:8000/api/administracion/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  | jq 'keys'

# Debería mostrar:
# [
#   "alumnosNuevos",
#   "distribucionProgramas",
#   "estadisticas",
#   "evolucionMatricula",
#   "graduaciones",
#   "matriculas",
#   "notificaciones",
#   "proximosInicios"
# ]
```

## Errores Comunes y Soluciones

### Error 401 Unauthorized
**Causa**: Token inválido o expirado
**Solución**: Generar nuevo token de autenticación

### Error 500 Internal Server Error
**Causa**: Error en base de datos o lógica del servidor
**Solución**: Revisar logs en `storage/logs/laravel.log`

```bash
tail -f storage/logs/laravel.log
```

### Sin datos en respuesta
**Causa**: Base de datos vacía o sin registros
**Solución**: Poblar base de datos con datos de prueba

## Monitoreo y Debugging

### Ver logs en tiempo real

```bash
# Laravel logs
tail -f storage/logs/laravel.log | grep -i dashboard

# PHP logs (si están habilitados)
tail -f /var/log/php-fpm/error.log
```

### Tiempo de respuesta

```bash
time curl -X GET \
  'http://localhost:8000/api/administracion/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -o /dev/null -s -w "Time: %{time_total}s\n"
```

## Variables de Entorno Recomendadas

En tu `.env`:

```env
# Cache para dashboard (opcional)
CACHE_DRIVER=redis
CACHE_PREFIX=asm_dashboard_

# Debug mode (solo desarrollo)
APP_DEBUG=true
APP_ENV=local

# Queue para exportaciones (opcional)
QUEUE_CONNECTION=redis
```

## Checklist de Pruebas

- [ ] Endpoint responde sin errores
- [ ] Autenticación funciona correctamente
- [ ] Datos de matrículas son correctos
- [ ] Datos de alumnos nuevos son correctos
- [ ] Próximos inicios cuenta cursos y periodos
- [ ] Graduaciones calcula próximo trimestre
- [ ] Evolución de matrícula muestra datos históricos
- [ ] Distribución por programas ordena correctamente
- [ ] Notificaciones muestran conteos correctos
- [ ] Estadísticas de múltiples programas funcionan
- [ ] Parámetro `periodo` funciona (6meses, 1año, todo)
- [ ] Exportación retorna formato correcto
- [ ] Manejo de errores funciona correctamente
- [ ] Tiempo de respuesta es aceptable (< 2s)

---

**Última actualización**: Octubre 2025
