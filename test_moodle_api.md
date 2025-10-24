# Pruebas de API de Moodle

## Token Actualizado
**Nuevo Token Moodle**: `47fc2203f86a7f5f6c9cb6052e87ea7b`

## Endpoints de Prueba de Moodle

### 1. Verificar Conexión con Moodle
```http
GET /api/moodle/test/connection
Authorization: Bearer {your_sanctum_token}
```

**Respuesta esperada**:
```json
{
  "success": true,
  "message": "✅ Conexión exitosa con Moodle",
  "data": {
    "sitename": "Campus American",
    "username": "...",
    "functions": [...]
  }
}
```

### 2. Listar Funciones Disponibles de Moodle
```http
GET /api/moodle/test/functions
Authorization: Bearer {your_sanctum_token}
```

### 3. Listar Cursos de Moodle
```http
GET /api/moodle/test/courses
Authorization: Bearer {your_sanctum_token}
```

### 4. Obtener Detalles de un Curso
```http
GET /api/moodle/test/courses/{moodle_course_id}
Authorization: Bearer {your_sanctum_token}
```

### 5. Crear Curso en Moodle (Para Pruebas)
```http
POST /api/moodle/test/courses
Authorization: Bearer {your_sanctum_token}
Content-Type: application/json

{
  "fullname": "Curso de Prueba API",
  "shortname": "TEST_API_001",
  "categoryid": 1,
  "summary": "Curso creado mediante API para pruebas",
  "startdate": 1729728000,
  "enddate": 1737590400
}
```

**Campos requeridos**:
- `fullname`: Nombre completo del curso
- `shortname`: Nombre corto (código único)
- `categoryid`: ID de la categoría en Moodle
- `summary`: Descripción del curso (opcional)
- `startdate`: Timestamp UNIX de fecha inicio
- `enddate`: Timestamp UNIX de fecha fin

### 6. Eliminar Curso de Moodle (Para Pruebas)
```http
DELETE /api/moodle/test/courses/{moodle_course_id}
Authorization: Bearer {your_sanctum_token}
```

### 7. Listar Categorías de Moodle
```http
GET /api/moodle/test/categories
Authorization: Bearer {your_sanctum_token}
```

---

## Endpoints de Cursos CRM (Sistema Local)

### 1. Listar Todos los Cursos del CRM
```http
GET /api/courses
Authorization: Bearer {your_sanctum_token}
```

**Query params opcionales**:
- `per_page`: Cantidad de resultados por página (default: 15)
- `page`: Número de página
- `program_id`: Filtrar por ID de programa

### 2. Obtener Cursos Disponibles para Estudiantes
```http
GET /api/courses/available-for-students?prospecto_ids[]=1&prospecto_ids[]=2
Authorization: Bearer {your_sanctum_token}
```

### 3. Obtener Cursos por Programas
```http
POST /api/courses/by-programs
Authorization: Bearer {your_sanctum_token}
Content-Type: application/json

{
  "program_ids": [1, 2, 3]
}
```

### 4. Crear Curso en el CRM
```http
POST /api/courses
Authorization: Bearer {your_sanctum_token}
Content-Type: application/json

{
  "name": "Introducción a la Programación",
  "code": "PROG101",
  "area": "common",
  "credits": 4,
  "start_date": "2025-01-15",
  "end_date": "2025-05-15",
  "schedule": "Lunes y Miércoles 18:00-20:00",
  "duration": "4 meses",
  "program_ids": [1, 2]
}
```

**Campos requeridos**:
- `name`: Nombre del curso
- `code`: Código único del curso
- `area`: "common", "specialty", o "closure"
- `credits`: Número de créditos
- `start_date`: Fecha de inicio (YYYY-MM-DD)
- `end_date`: Fecha de finalización (YYYY-MM-DD)
- `schedule`: Horario del curso
- `duration`: Duración textual
- `program_ids`: Array de IDs de programas

### 5. Aprobar Curso
```http
POST /api/courses/{course_id}/approve
Authorization: Bearer {your_sanctum_token}
```

### 6. Sincronizar Curso con Moodle
```http
POST /api/courses/{course_id}/sync-moodle
Authorization: Bearer {your_sanctum_token}
```

### 7. Asignar Facilitador a Curso
```http
POST /api/courses/{course_id}/assign-facilitator
Authorization: Bearer {your_sanctum_token}
Content-Type: application/json

{
  "facilitator_id": 5
}
```

### 8. Asignar Cursos a Estudiantes
```http
POST /api/courses/assign
Authorization: Bearer {your_sanctum_token}
Content-Type: application/json

{
  "course_ids": [1, 2, 3],
  "prospecto_ids": [10, 11]
}
```

### 9. Desasignar Cursos de Estudiantes
```http
POST /api/courses/unassign
Authorization: Bearer {your_sanctum_token}
Content-Type: application/json

{
  "course_ids": [1, 2],
  "prospecto_ids": [10]
}
```

---

## Flujo de Trabajo Completo

### Escenario 1: Crear y Sincronizar Curso con Moodle

1. **Crear curso en CRM**:
   ```http
   POST /api/courses
   {
     "name": "Matemáticas Financieras",
     "code": "MAT201",
     ...
   }
   ```

2. **Aprobar curso**:
   ```http
   POST /api/courses/1/approve
   ```

3. **Sincronizar con Moodle**:
   ```http
   POST /api/courses/1/sync-moodle
   ```

### Escenario 2: Verificar Integración con Moodle

1. **Probar conexión**:
   ```http
   GET /api/moodle/test/connection
   ```

2. **Listar categorías disponibles**:
   ```http
   GET /api/moodle/test/categories
   ```

3. **Crear curso de prueba en Moodle**:
   ```http
   POST /api/moodle/test/courses
   {
     "fullname": "Curso Prueba",
     "shortname": "TEST001",
     "categoryid": 1
   }
   ```

4. **Verificar creación**:
   ```http
   GET /api/moodle/test/courses
   ```

5. **Eliminar curso de prueba**:
   ```http
   DELETE /api/moodle/test/courses/{moodle_id}
   ```

---

## Notas Importantes

1. **Autenticación**: Todos los endpoints requieren token Sanctum en header `Authorization: Bearer {token}`

2. **Timestamps UNIX**: Para fechas en Moodle usar formato timestamp UNIX:
   - JavaScript: `Math.floor(new Date('2025-01-15').getTime() / 1000)`
   - PHP: `strtotime('2025-01-15')`

3. **IDs de Categoría**: Obtener primero con `/api/moodle/test/categories` antes de crear cursos

4. **Estados de Cursos en CRM**:
   - `draft`: Borrador (recién creado)
   - `approved`: Aprobado (listo para sincronizar)
   - `synced`: Sincronizado con Moodle

5. **Logs**: Todos los endpoints de Moodle test tienen logging detallado. Revisar `storage/logs/laravel.log`

---

## Solución de Problemas

### Frontend se queda cargando cursos

**Problema**: El componente de cursos se queda en estado de carga infinita.

**Causas posibles**:
1. Token de autenticación expirado o inválido
2. Backend no responde
3. Rutas no están cacheadas correctamente
4. Error en el endpoint `/api/courses`

**Solución**:
1. Verificar token en localStorage/sessionStorage del navegador
2. Verificar que el backend esté corriendo
3. Limpiar caché de rutas: `php artisan route:cache`
4. Verificar logs de Laravel: `storage/logs/laravel.log`
5. Probar endpoint manualmente:
   ```bash
   curl -H "Authorization: Bearer {token}" http://localhost:8000/api/courses
   ```

### Error al sincronizar con Moodle

**Problema**: Error al llamar `/api/courses/{id}/sync-moodle`

**Verificar**:
1. Token de Moodle configurado correctamente en `.env`
2. Conexión con Moodle: `GET /api/moodle/test/connection`
3. Curso está en estado `approved`
4. Revisar logs para ver error específico de Moodle

---

## Comandos Útiles

```bash
# Limpiar todas las cachés
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Cachear configuración y rutas
php artisan config:cache
php artisan route:cache

# Ver rutas de cursos
php artisan route:list --path=courses

# Ver rutas de Moodle
php artisan route:list --path=moodle

# Ver logs en tiempo real
tail -f storage/logs/laravel.log
```
