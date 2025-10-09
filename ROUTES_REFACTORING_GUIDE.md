# Guía de Refactorización de Rutas API

## Resumen

Este documento describe la refactorización completa del archivo `routes/api.php` para mejorar la organización, claridad y mantenibilidad del código, manteniendo 100% de compatibilidad hacia atrás con el frontend existente.

## Cambios Implementados

### 1. Consolidación de Health Checks

**Antes:**
- Múltiples endpoints dispersos: `/ping`, `/status`, `/version`, `/time`, `/db-status`, `/health`

**Después:**
- Endpoint principal: `/health` (consolidado con toda la información)
- Alias mantenido: `/ping` (para compatibilidad)
- Eliminados: `/status`, `/version`, `/time`, `/db-status` (redundantes)

**Beneficio:** Reduce redundancia y proporciona un único punto de entrada para verificaciones de salud.

### 2. Estandarización de Prefijos

**Antes:**
- Mixto entre "conciliacion" y "reconciliation"
- Rutas duplicadas: `/conciliacion/import`, `/reconciliation/upload`

**Después:**
- Estandarizado a `/conciliacion/*` en español
- Consolidadas todas las rutas de conciliación bajo un mismo prefijo
- Rutas organizadas:
  - `POST /conciliacion/import`
  - `POST /conciliacion/import-kardex`
  - `GET /conciliacion/template`
  - `GET /conciliacion/export`
  - `GET /conciliacion/pendientes-desde-kardex`
  - `GET /conciliacion/conciliados-desde-kardex`

**Beneficio:** Consistencia de nombres y eliminación de rutas duplicadas.

### 3. Organización por Dominios

El archivo ahora está organizado en secciones claramente definidas:

#### A. Rutas Públicas
- Health checks
- Emails públicos
- Consultas públicas admin
- Autenticación (`/login`, `/logout`)
- Consultas públicas de prospectos
- Inscripciones

#### B. Rutas Protegidas (auth:sanctum)

##### DOMINIO: PROSPECTOS Y SEGUIMIENTO
- **Prospectos**: CRUD completo + acciones masivas + consultas especiales
- **Documentos**: Gestión de documentos de prospectos
- **Configuración de columnas**: Para personalización de tablas
- **Duplicados**: Detección y gestión de duplicados
- **Actividades**: Gestión de actividades
- **Citas**: Gestión de citas
- **Interacciones**: Gestión de interacciones
- **Tareas**: Gestión de tareas
- **Correos**: Envío de correos
- **Comisiones**: Gestión de comisiones

##### DOMINIO: ACADÉMICO
- **Estudiantes**: Importación
- **Programas académicos**: CRUD + precios
- **Estudiante-Programa**: Relación estudiante-programa
- **Cursos**: Gestión completa de cursos
- **Estudiantes**: Listados y detalles
- **Ranking**: Rendimiento académico
- **Moodle**: Consultas a Moodle

##### DOMINIO: FINANCIERO
- **Dashboard financiero**: Métricas y reportes
- **Conciliación bancaria**: Importación y procesamiento
- **Facturas**: CRUD de facturas
- **Pagos**: Gestión de pagos
- **Cuotas**: Planes de pago
- **Kardex**: Histórico de pagos
- **Reglas de pago**: Configuración de reglas
- **Pasarelas de pago**: Gestión de pasarelas
- **Categorías de excepción**: Gestión de excepciones
- **Portal estudiante**: Pagos y recibos
- **Gestión de cobros**: Administración de cobros
- **Logs de cobro**: Histórico
- **Reportes financieros**: Exportaciones

##### DOMINIO: ADMINISTRACIÓN
- **Sesiones**: Gestión de sesiones de usuario
- **Usuarios**: CRUD completo + permisos
- **Roles**: CRUD + asignación de permisos
- **Permisos**: Gestión de permisos
- **Módulos y vistas**: Configuración de sistema
- **Flujos de aprobación**: Workflows

#### C. Rutas de Recursos Adicionales
- Periodos de inscripción
- Contactos enviados
- Ubicación
- Convenios
- Precios
- Reglas (legacy)

## Mejoras Implementadas

### 1. Comentarios Descriptivos
Cada sección tiene encabezados claros que explican su propósito:
```php
// ============================================
// DOMINIO: PROSPECTOS Y SEGUIMIENTO
// ============================================
```

### 2. Agrupación Lógica
Las rutas relacionadas están agrupadas usando `Route::prefix()`:
```php
Route::prefix('conciliacion')->group(function () {
    // Todas las rutas de conciliación aquí
});
```

### 3. Eliminación de Redundancias
- Rutas duplicadas eliminadas
- Endpoints consolidados
- Prefijos estandarizados

### 4. Restricciones Mejoradas
Uso consistente de restricciones para IDs numéricos:
```php
->where('id', '[0-9]+')
// o
->whereNumber('id')
```

### 5. Orden Estratégico
Las rutas estáticas se definen antes que las dinámicas para evitar conflictos:
```php
Route::get('/available-for-students', ...);  // Primero
Route::get('/{course}', ...)->whereNumber('course');  // Después
```

## Compatibilidad Hacia Atrás

✅ **Todas las rutas utilizadas por el frontend siguen funcionando:**

- `GET /documentos`
- `PUT /documentos/{id}`
- `POST /estudiantes/import`
- `GET /users`
- `PUT /tareas/{id}`, `POST /tareas`, `DELETE /tareas/{id}`
- `PUT /citas/{id}`, `POST /citas`, `DELETE /citas/{id}`
- `POST /login`
- `GET /prospectos`
- `GET /interacciones`, `POST /interacciones`
- `GET /citas`
- `GET /actividades`
- `POST /logout`
- `GET /programas`, `POST /programas`, `DELETE /programas/{id}`, `PUT /programas/{id}`
- `GET /courses`, `POST /courses`, `PUT /courses/{id}`, `DELETE /courses/{id}`
- `POST /courses/{id}/approve`, `POST /courses/{id}/sync-moodle`
- `POST /courses/{id}/assign-facilitator`
- `GET /users/role/2`
- `POST /courses/by-programs`
- `GET /estudiante-programa/{studentId}/with-courses`

## Rutas Eliminadas (Sin Uso en Frontend)

- `GET /status` → Consolidado en `/health`
- `GET /version` → Consolidado en `/health`
- `GET /time` → Consolidado en `/health`
- `GET /db-status` → Consolidado en `/health`
- `POST /reconciliation/upload` → Consolidado en `/conciliacion/import`
- `GET /reconciliation/pending` → Movido a `/conciliacion/pendientes-desde-kardex`
- `POST /reconciliation/process` → Consolidado en `/conciliacion/import`

## Recomendaciones para Desarrollo Futuro

### 1. Agregar Nuevas Rutas
Sigue la estructura de dominios al agregar nuevas rutas:
```php
// ============================================
// DOMINIO: [NOMBRE DEL DOMINIO]
// ============================================

Route::prefix('nuevo-recurso')->group(function () {
    Route::get('/', [Controller::class, 'index']);
    Route::post('/', [Controller::class, 'store']);
    // ...
});
```

### 2. Mantener Consistencia
- Usa prefijos en español para recursos en español
- Agrupa rutas relacionadas con `Route::prefix()`
- Coloca rutas protegidas dentro del middleware `auth:sanctum`
- Usa restricciones `whereNumber()` para IDs

### 3. Documentar Cambios
Actualiza este documento cuando agregues nuevas secciones o dominios.

## Verificación

Para verificar que todas las rutas funcionan correctamente:

```bash
# Ver listado de todas las rutas
php artisan route:list

# Filtrar por método
php artisan route:list --method=GET

# Filtrar por nombre
php artisan route:list --name=prospectos
```

## Testing

Se recomienda ejecutar los tests existentes para verificar compatibilidad:

```bash
php artisan test
```

## Conclusión

Esta refactorización proporciona:
- ✅ Mayor claridad y organización
- ✅ Eliminación de redundancias
- ✅ Mejor mantenibilidad
- ✅ Facilidad para escalar
- ✅ 100% de compatibilidad hacia atrás
- ✅ Estructura clara por dominios

El código ahora es más fácil de entender, mantener y extender para futuros desarrolladores.
