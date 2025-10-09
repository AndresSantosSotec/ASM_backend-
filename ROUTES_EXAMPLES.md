# Ejemplos de Mejoras en la Refactorización

Este documento muestra ejemplos específicos del antes y después de la refactorización de rutas.

## Ejemplo 1: Health Checks - De Disperso a Consolidado

### ❌ ANTES (Disperso y Redundante)

```php
// En diferentes partes del archivo...

Route::get('/ping', fn() => response()->json(['message' => 'pong!']));

route::get('/status', function () {
    return response()->json(['status' => 'API is running']);
});

Route::get('/version', function () {
    return response()->json(['version' => '1.0.0']);
});

Route::get('/time', function () {
    return response()->json(['time' => now()->toDateTimeString()]);
});

Route::get('/db-status', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'db'     => 'connected',
            'status' => 'ok',
            'time'   => now()->toDateTimeString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'db'     => 'disconnected',
            'status' => 'error',
            'error'  => 'No se pudo conectar a la base de datos',
            'time'   => now()->toDateTimeString(),
        ], 500);
    }
});

// Más adelante en el archivo...
Route::get('/health', function () {
    // ... código de health check
});
```

### ✅ DESPUÉS (Consolidado y Organizado)

```php
/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
| Rutas accesibles sin autenticación
*/

// ============================================
// Health Check y Status (consolidados)
// ============================================
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        $basic = [
            'status' => 'API is healthy',
            'ok'     => true,
            'time'   => now()->toDateTimeString(),
            'version'=> '1.0.0',  // ← Incluye versión
        ];

        // Entorno de desarrollo: información ampliada
        if (app()->environment(['local', 'testing'])) {
            $basic['meta'] = [
                'app'     => config('app.name'),
                'db'      => ['connected' => true],  // ← Incluye DB status
            ];
            Log::info('Healthcheck successful (dev)', ['env' => app()->environment(), 'ok' => true]);
        } else {
            Log::info('Healthcheck successful', ['ok' => true]);
        }

        return response()->json($basic);
    } catch (\Throwable $e) {
        Log::error('Healthcheck failed', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'API is unhealthy',
            'ok'     => false,
            'time'   => now()->toDateTimeString(),
        ], 500);
    }
});

// Alias para compatibilidad
Route::get('/ping', fn() => response()->json(['message' => 'pong!', 'ok' => true]));
```

**Beneficio**: 6 endpoints → 2 endpoints. Toda la información en un solo lugar.

---

## Ejemplo 2: Conciliación - De Duplicado a Estandarizado

### ❌ ANTES (Duplicados y Mixto Español/Inglés)

```php
// Dentro del middleware auth:sanctum, al inicio
Route::post('/conciliacion/import', [ReconciliationController::class, 'import']);
Route::get('/conciliacion/template', [ReconciliationController::class, 'downloadTemplate']);
Route::get('/conciliacion/export', [ReconciliationController::class, 'export']);
Route::get('/conciliacion/pendientes-desde-kardex', [ReconciliationController::class, 'kardexNoConciliados']);

// Más adelante, en otro grupo middleware...
Route::post('/reconciliation/upload', [ReconciliationController::class, 'upload']);  // ← DUPLICADO
Route::get('/reconciliation/pending', [ReconciliationController::class, 'pending']);  // ← DUPLICADO
Route::post('/reconciliation/process', [ReconciliationController::class, 'process']); // ← DUPLICADO
Route::get('/conciliacion/conciliados-desde-kardex', [ReconciliationController::class, 'kardexConciliados']);

// Al final del archivo, fuera de middleware
Route::post('/conciliacion/import-kardex', [ReconciliationController::class, 'ImportarPagosKardex']);
```

### ✅ DESPUÉS (Consolidado y Estandarizado)

```php
// ============================================
// DOMINIO: FINANCIERO
// ============================================

// Conciliación bancaria (estandarizado a 'conciliacion')
Route::prefix('conciliacion')->group(function () {
    Route::post('/import', [ReconciliationController::class, 'import']);
    Route::post('/import-kardex', [ReconciliationController::class, 'ImportarPagosKardex']);
    Route::get('/template', [ReconciliationController::class, 'downloadTemplate']);
    Route::get('/export', [ReconciliationController::class, 'export']);
    Route::get('/pendientes-desde-kardex', [ReconciliationController::class, 'kardexNoConciliados']);
    Route::get('/conciliados-desde-kardex', [ReconciliationController::class, 'kardexConciliados']);
});
```

**Beneficio**: 
- ✅ Todas las rutas en un solo lugar
- ✅ Prefijo consistente en español
- ✅ Duplicados eliminados (9 rutas → 6 rutas)
- ✅ Agrupación lógica con `Route::prefix()`

---

## Ejemplo 3: Organización por Dominios

### ❌ ANTES (Sin Organización Clara)

```php
Route::middleware('auth:sanctum')->group(function () {
    // Conciliación al inicio (¿por qué aquí?)
    Route::post('/conciliacion/import', ...);
    
    // Prospectos
    Route::prefix('prospectos')->group(...);
    
    // Sesiones (¿relacionado con prospectos?)
    Route::prefix('sessions')->group(...);
    
    // Citas
    Route::prefix('citas')->group(...);
    
    // ... más rutas mezcladas
});

// Programas fuera del middleware (¿público?)
Route::prefix('programas')->group(...);

// Usuarios fuera del middleware (¿público?)
Route::prefix('users')->group(...);

// Otro middleware group con finanzas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard-financiero', ...);
    // ...
});
```

### ✅ DESPUÉS (Organizado por Dominios)

```php
Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // DOMINIO: PROSPECTOS Y SEGUIMIENTO
    // ============================================
    Route::prefix('prospectos')->group(function () { /* ... */ });
    Route::prefix('documentos')->group(function () { /* ... */ });
    Route::prefix('actividades')->group(function () { /* ... */ });
    Route::prefix('citas')->group(function () { /* ... */ });
    Route::prefix('interacciones')->group(function () { /* ... */ });
    Route::prefix('tareas')->group(function () { /* ... */ });
    Route::prefix('commissions')->group(function () { /* ... */ });

    // ============================================
    // DOMINIO: ACADÉMICO
    // ============================================
    Route::prefix('estudiantes')->group(function () { /* ... */ });
    Route::prefix('programas')->group(function () { /* ... */ });
    Route::prefix('estudiante-programa')->group(function () { /* ... */ });
    Route::prefix('courses')->group(function () { /* ... */ });
    Route::get('/students', ...);
    Route::get('/ranking/students', ...);
    Route::prefix('moodle')->group(function () { /* ... */ });

    // ============================================
    // DOMINIO: FINANCIERO
    // ============================================
    Route::get('/dashboard-financiero', ...);
    Route::prefix('conciliacion')->group(function () { /* ... */ });
    Route::prefix('invoices')->group(function () { /* ... */ });
    Route::prefix('payments')->group(function () { /* ... */ });
    Route::prefix('payment-rules')->group(function () { /* ... */ });
    Route::prefix('payment-gateways')->group(function () { /* ... */ });
    Route::prefix('estudiante/pagos')->group(function () { /* ... */ });
    Route::prefix('collections')->group(function () { /* ... */ });

    // ============================================
    // DOMINIO: ADMINISTRACIÓN
    // ============================================
    Route::prefix('sessions')->group(function () { /* ... */ });
    Route::prefix('users')->group(function () { /* ... */ });
    Route::prefix('roles')->group(function () { /* ... */ });
    Route::prefix('modules')->group(function () { /* ... */ });
    Route::prefix('approval-flows')->group(function () { /* ... */ });
});
```

**Beneficio**: 
- ✅ Agrupación lógica por dominio de negocio
- ✅ Fácil navegar y encontrar rutas
- ✅ Un solo grupo middleware principal
- ✅ Comentarios descriptivos claros

---

## Ejemplo 4: Orden de Rutas - Evitar Conflictos

### ❌ ANTES (Potencial para conflictos)

```php
Route::prefix('prospectos')->group(function () {
    Route::get('/{id}', [ProspectoController::class, 'show']);  // ← Puede atrapar 'status'
    Route::get('/status/{status}', [ProspectoController::class, 'filterByStatus']);
    Route::get('/fichas/pendientes', [ProspectoController::class, 'pendientesAprobacion']);
    Route::put('/bulk-update-status', [ProspectoController::class, 'bulkUpdateStatus']);
    Route::get('pendientes-con-docs', [ProspectoController::class, 'pendientesConDocs']);
});
```

### ✅ DESPUÉS (Orden Estratégico)

```php
Route::prefix('prospectos')->group(function () {
    Route::get('/', [ProspectoController::class, 'index']);
    Route::post('/', [ProspectoController::class, 'store']);
    
    // Acciones masivas primero (rutas fijas)
    Route::put('/bulk-assign', [ProspectoController::class, 'bulkAssign']);
    Route::put('/bulk-update-status', [ProspectoController::class, 'bulkUpdateStatus']);
    Route::delete('/bulk-delete', [ProspectoController::class, 'bulkDelete']);
    
    // Consultas especiales (rutas fijas)
    Route::get('/status/{status}', [ProspectoController::class, 'filterByStatus']);
    Route::get('/fichas/pendientes', [ProspectoController::class, 'pendientesAprobacion']);
    Route::get('/pendientes-con-docs', [ProspectoController::class, 'pendientesConDocs']);
    Route::get('/inscritos-with-courses', [ProspectoController::class, 'inscritosConCursos']);
    
    // Rutas dinámicas al final (con restricción numérica)
    Route::get('/{id}', [ProspectoController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [ProspectoController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [ProspectoController::class, 'destroy'])->where('id', '[0-9]+');
});
```

**Beneficio**: 
- ✅ Rutas estáticas primero
- ✅ Rutas dinámicas al final con restricciones
- ✅ Sin conflictos de rutas
- ✅ Orden lógico de operaciones (CRUD)

---

## Ejemplo 5: Comentarios Descriptivos

### ❌ ANTES (Mínimos comentarios)

```php
// ----------------------
// Usuarios
// ----------------------/me
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    // ... más rutas
});
```

### ✅ DESPUÉS (Comentarios Extensivos)

```php
// ============================================
// DOMINIO: ADMINISTRACIÓN
// ============================================

// Usuarios
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
    Route::post('/{id}/restore', [UserController::class, 'restore']);
    Route::put('/bulk-update', [UserController::class, 'bulkUpdate']);
    Route::post('/bulk-delete', [UserController::class, 'bulkDelete']);
    Route::get('/export', [UserController::class, 'export']);
    Route::get('/role/{roleId}', [UserController::class, 'getUsersByRole']);
    Route::post('/{id}/assign-permissions', [UserController::class, 'assignPermissions']);
});
```

**Beneficio**: 
- ✅ Contexto claro del dominio
- ✅ Separadores visuales distintivos
- ✅ Fácil identificar la sección

---

## Resumen de Patrones Mejorados

### Patrón 1: Agrupación con Prefijos
```php
// ✅ MEJOR
Route::prefix('conciliacion')->group(function () {
    Route::post('/import', ...);        // /conciliacion/import
    Route::get('/template', ...);       // /conciliacion/template
});

// ❌ EVITAR
Route::post('/conciliacion/import', ...);
Route::get('/conciliacion/template', ...);
```

### Patrón 2: Rutas Estáticas Primero
```php
// ✅ MEJOR
Route::get('/available-for-students', ...);  // Estática primero
Route::get('/{id}', ...)->whereNumber('id'); // Dinámica después

// ❌ EVITAR
Route::get('/{id}', ...);                    // Atrapa todo
Route::get('/available-for-students', ...);  // Nunca se alcanza
```

### Patrón 3: Restricciones Numéricas
```php
// ✅ MEJOR
Route::get('/{id}', ...)->where('id', '[0-9]+');
// o
Route::get('/{id}', ...)->whereNumber('id');

// ❌ EVITAR
Route::get('/{id}', ...);  // Sin restricción
```

### Patrón 4: Un Solo Middleware Group
```php
// ✅ MEJOR
Route::middleware('auth:sanctum')->group(function () {
    // Todas las rutas protegidas aquí
});

// ❌ EVITAR
Route::middleware('auth:sanctum')->group(function () { /* ... */ });
// ...otras rutas...
Route::middleware('auth:sanctum')->group(function () { /* ... */ }); // ← Segundo grupo
```

---

## Impacto en Mantenimiento

### Antes: Agregar una nueva ruta de reportes
```php
// ¿Dónde pongo esto?
// ¿Va en el primer middleware group?
// ¿O en el segundo?
// ¿Es parte de finanzas o administración?
Route::get('/reports/ventas', ...);  // ← Sin contexto
```

### Después: Agregar una nueva ruta de reportes
```php
// ============================================
// DOMINIO: FINANCIERO
// ============================================

// Reportes financieros
Route::prefix('reports')->group(function () {
    Route::get('/summary', [ReportsController::class, 'summary']);
    Route::get('/export', [ReportsController::class, 'export']);
    Route::get('/ventas', [ReportsController::class, 'ventas']);  // ← Lugar obvio
});
```

**Beneficio**: Ubicación obvia, contexto claro, fácil de mantener.

---

## Conclusión

La refactorización no solo reduce líneas de código, sino que crea un código más:
- 📖 **Legible**: Fácil de entender
- 🔧 **Mantenible**: Fácil de modificar
- 🚀 **Escalable**: Fácil de extender
- ✅ **Confiable**: Sin breaking changes

Estos ejemplos demuestran cómo pequeños cambios de organización resultan en grandes mejoras de calidad del código.
