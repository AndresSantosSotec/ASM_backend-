# Estructura Visual de Rutas API

```
routes/api.php
│
├─ 📦 IMPORTS (Líneas 1-67)
│   └─ Todos los controladores necesarios
│
├─ 🌐 RUTAS PÚBLICAS (Líneas 68-139)
│   │
│   ├─ ⚕️ Health Check y Status (consolidados)
│   │   ├─ GET  /health         → Verificación completa de salud
│   │   └─ GET  /ping           → Alias simple
│   │
│   ├─ 📧 Emails Públicos
│   │   ├─ POST /emails/send
│   │   └─ POST /emails/send-queued
│   │
│   ├─ 👥 Consultas Públicas Admin
│   │   └─ /admin
│   │       ├─ GET  /prospectos
│   │       ├─ GET  /prospectos/{id}/estado-cuenta
│   │       └─ GET  /prospectos/{id}/historial
│   │
│   ├─ 🔐 Autenticación
│   │   ├─ POST /login
│   │   └─ POST /logout (auth:sanctum)
│   │
│   ├─ 🔍 Consultas Públicas de Prospectos
│   │   ├─ GET  /prospectos/{id}
│   │   └─ GET  /prospectos/fichas/pendientes-public
│   │
│   └─ 📝 Inscripciones
│       ├─ POST /plan-pagos/generar
│       ├─ POST /inscripciones/finalizar
│       └─ GET  /fichas/{id} (auth:sanctum)
│
└─ 🔒 RUTAS PROTEGIDAS [auth:sanctum] (Líneas 140-591)
    │
    ├─ 👤 Usuario Autenticado
    │   └─ GET  /user → Datos del usuario actual
    │
    ├─ ═════════════════════════════════════════════════════════
    │  👥 DOMINIO: PROSPECTOS Y SEGUIMIENTO (Líneas 178-297)
    ├─ ═════════════════════════════════════════════════════════
    │   │
    │   ├─ 📋 Prospectos (/prospectos)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /bulk-assign         → Asignación masiva
    │   │   ├─ PUT    /bulk-update-status  → Actualización masiva
    │   │   ├─ DELETE /bulk-delete         → Eliminación masiva
    │   │   ├─ GET    /status/{status}     → Filtrar por estado
    │   │   ├─ GET    /fichas/pendientes   → Fichas pendientes
    │   │   ├─ GET    /pendientes-con-docs → Con documentos
    │   │   ├─ GET    /inscritos-with-courses → Con cursos
    │   │   ├─ GET    /{id}                → Ver uno
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   ├─ DELETE /{id}                → Eliminar
    │   │   ├─ PUT    /{id}/status         → Cambiar estado
    │   │   ├─ PUT    /{id}/assign         → Asignar
    │   │   ├─ POST   /{id}/enviar-contrato → Enviar contrato
    │   │   └─ GET    /{id}/download-contrato → Descargar contrato
    │   │
    │   ├─ 📤 Importación
    │   │   └─ POST   /import              → Importar prospectos
    │   │
    │   ├─ 📄 Documentos (/documentos)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Subir
    │   │   ├─ GET    /{id}                → Ver
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   ├─ DELETE /{id}                → Eliminar
    │   │   ├─ GET    /{id}/file           → Descargar archivo
    │   │   └─ GET    /prospecto/{prospectoId} → Por prospecto
    │   │
    │   ├─ ⚙️ Configuración de Columnas (/columns)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   ├─ 👥 Duplicados (/duplicates)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /detect              → Detectar
    │   │   ├─ POST   /{duplicate}/action  → Acción sobre uno
    │   │   └─ POST   /bulk-action         → Acción masiva
    │   │
    │   ├─ 📅 Actividades (/actividades)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ GET    /{id}                → Ver
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   ├─ 📆 Citas (/citas)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ GET    /{id}                → Ver
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   ├─ 💬 Interacciones (/interacciones)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ GET    /{id}                → Ver
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   ├─ ✅ Tareas (/tareas)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ GET    /{id}                → Ver
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   ├─ 📧 Correos
    │   │   └─ POST   /enviar-correo       → Enviar
    │   │
    │   └─ 💰 Comisiones (/commissions)
    │       ├─ GET    /config              → Ver configuración
    │       ├─ POST   /config              → Crear configuración
    │       ├─ PUT    /config              → Actualizar configuración
    │       ├─ GET    /rates/{userId}      → Tasas por usuario
    │       ├─ POST   /rates               → Crear tasa
    │       ├─ PUT    /rates/{userId}      → Actualizar tasa
    │       ├─ GET    /                    → Listar comisiones
    │       ├─ POST   /                    → Crear comisión
    │       ├─ GET    /{id}                → Ver comisión
    │       ├─ PUT    /{id}                → Actualizar comisión
    │       ├─ DELETE /{id}                → Eliminar comisión
    │       └─ GET    /report              → Reporte
    │
    ├─ ═════════════════════════════════════════════════════════
    │  🎓 DOMINIO: ACADÉMICO (Líneas 298-371)
    ├─ ═════════════════════════════════════════════════════════
    │   │
    │   ├─ 👨‍🎓 Estudiantes (/estudiantes)
    │   │   └─ POST   /import              → Importar estudiantes
    │   │
    │   ├─ 📚 Programas (/programas)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   ├─ DELETE /{id}                → Eliminar
    │   │   ├─ GET    /{programaId}/precios → Ver precios
    │   │   └─ PUT    /{programaId}/precios → Actualizar precios
    │   │
    │   ├─ 🎓 Estudiante-Programa (/estudiante-programa)
    │   │   ├─ GET    /                    → Por prospecto (query)
    │   │   ├─ GET    /all                 → Listar todos
    │   │   ├─ GET    /{id}                → Ver uno
    │   │   ├─ GET    /{id}/with-courses   → Con cursos
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   ├─ 📖 Cursos (/courses)
    │   │   ├─ GET    /available-for-students → Disponibles
    │   │   ├─ POST   /assign              → Asignar
    │   │   ├─ POST   /unassign            → Desasignar
    │   │   ├─ POST   /bulk-assign         → Asignación masiva
    │   │   ├─ POST   /bulk-sync-moodle    → Sync masivo a Moodle
    │   │   ├─ POST   /by-programs         → Por programas
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ POST   /{course}/approve    → Aprobar
    │   │   ├─ POST   /{course}/sync-moodle → Sincronizar a Moodle
    │   │   ├─ POST   /{course}/assign-facilitator → Asignar facilitador
    │   │   ├─ GET    /{course}            → Ver
    │   │   ├─ PUT    /{course}            → Actualizar
    │   │   └─ DELETE /{course}            → Eliminar
    │   │
    │   ├─ 👨‍🎓 Estudiantes
    │   │   ├─ GET    /students            → Listar
    │   │   └─ GET    /students/{id}       → Ver uno
    │   │
    │   ├─ 🏆 Ranking
    │   │   ├─ GET    /ranking/students    → Ranking estudiantes
    │   │   ├─ GET    /ranking/courses     → Rendimiento cursos
    │   │   └─ GET    /ranking/report      → Reporte
    │   │
    │   └─ 🎮 Moodle (/moodle)
    │       ├─ GET    /consultas/{carnet?} → Cursos por carnet
    │       ├─ GET    /consultas/aprobados/{carnet?} → Aprobados
    │       ├─ GET    /consultas/reprobados/{carnet?} → Reprobados
    │       ├─ GET    /consultas/estatus/{carnet?} → Estatus académico
    │       └─ GET    /programacion-cursos → Programación
    │
    ├─ ═════════════════════════════════════════════════════════
    │  💰 DOMINIO: FINANCIERO (Líneas 372-495)
    ├─ ═════════════════════════════════════════════════════════
    │   │
    │   ├─ 📊 Dashboard
    │   │   └─ GET    /dashboard-financiero → Métricas
    │   │
    │   ├─ 🏦 Conciliación (/conciliacion)
    │   │   ├─ POST   /import              → Importar archivo
    │   │   ├─ POST   /import-kardex       → Importar desde kardex
    │   │   ├─ GET    /template            → Descargar plantilla
    │   │   ├─ GET    /export              → Exportar registros
    │   │   ├─ GET    /pendientes-desde-kardex → Pendientes
    │   │   └─ GET    /conciliados-desde-kardex → Conciliados
    │   │
    │   ├─ 🧾 Facturas (/invoices)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ PUT    /{invoice}           → Actualizar
    │   │   └─ DELETE /{invoice}           → Eliminar
    │   │
    │   ├─ 💳 Pagos (/payments)
    │   │   ├─ GET    /                    → Listar
    │   │   └─ POST   /                    → Crear
    │   │
    │   ├─ 💵 Cuotas
    │   │   ├─ GET    /prospectos/{id}/cuotas → Por prospecto
    │   │   └─ GET    /estudiante-programa/{id}/cuotas → Por programa
    │   │
    │   ├─ 📖 Kardex
    │   │   ├─ GET    /kardex-pagos        → Listar
    │   │   └─ POST   /kardex-pagos        → Crear
    │   │
    │   ├─ ⚙️ Reglas de Pago (/payment-rules)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ GET    /{rule}              → Ver
    │   │   ├─ PUT    /{rule}              → Actualizar
    │   │   ├─ [Notificaciones] /{rule}/notifications
    │   │   └─ [Reglas de Bloqueo] /{rule}/blocking-rules
    │   │
    │   ├─ 🔌 Pasarelas (/payment-gateways)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ GET    /active              → Activas
    │   │   ├─ GET    /{gateway}           → Ver
    │   │   ├─ PUT    /{gateway}           → Actualizar
    │   │   ├─ DELETE /{gateway}           → Eliminar
    │   │   └─ PATCH  /{gateway}/toggle-status → Activar/desactivar
    │   │
    │   ├─ 🚫 Excepciones (/payment-exception-categories)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ GET    /{category}          → Ver
    │   │   ├─ PUT    /{category}          → Actualizar
    │   │   ├─ DELETE /{category}          → Eliminar
    │   │   ├─ PATCH  /{category}/toggle-status → Activar/desactivar
    │   │   ├─ POST   /{category}/assign-prospecto → Asignar
    │   │   ├─ DELETE /{category}/remove-prospecto → Remover
    │   │   ├─ GET    /{category}/assigned-prospectos → Ver asignados
    │   │   └─ POST   /{category}/assign-student → Legacy
    │   │
    │   ├─ 👨‍🎓 Portal Estudiante (/estudiante/pagos)
    │   │   ├─ GET    /pendientes          → Pagos pendientes
    │   │   ├─ GET    /historial           → Historial
    │   │   ├─ GET    /estado-cuenta       → Estado de cuenta
    │   │   ├─ POST   /subir-recibo        → Subir recibo
    │   │   └─ POST   /prevalidar-recibo   → Prevalidar
    │   │
    │   ├─ 💼 Gestión de Cobros (/collections)
    │   │   ├─ GET    /late-payments       → Pagos atrasados
    │   │   ├─ GET    /students/{epId}/snapshot → Snapshot estudiante
    │   │   ├─ GET    /payment-plans       → Planes de pago
    │   │   ├─ POST   /payment-plans/preview → Preview plan
    │   │   └─ POST   /payment-plans       → Crear plan
    │   │
    │   ├─ 📝 Logs de Cobro (/collection-logs)
    │   │   ├─ GET    /                    → Listar
    │   │   ├─ POST   /                    → Crear
    │   │   ├─ GET    /{id}                → Ver
    │   │   ├─ PUT    /{id}                → Actualizar
    │   │   └─ DELETE /{id}                → Eliminar
    │   │
    │   └─ 📊 Reportes (/reports)
    │       ├─ GET    /summary             → Resumen
    │       └─ GET    /export              → Exportar
    │
    └─ ═════════════════════════════════════════════════════════
       ⚙️ DOMINIO: ADMINISTRACIÓN (Líneas 496-591)
    └─ ═════════════════════════════════════════════════════════
        │
        ├─ 🔐 Sesiones (/sessions)
        │   ├─ GET    /                    → Listar
        │   ├─ PUT    /{id}/close          → Cerrar una
        │   └─ PUT    /close-all           → Cerrar todas
        │
        ├─ 👥 Usuarios (/users)
        │   ├─ GET    /                    → Listar
        │   ├─ GET    /{id}                → Ver
        │   ├─ POST   /                    → Crear
        │   ├─ PUT    /{id}                → Actualizar
        │   ├─ DELETE /{id}                → Eliminar
        │   ├─ POST   /{id}/restore        → Restaurar
        │   ├─ PUT    /bulk-update         → Actualización masiva
        │   ├─ POST   /bulk-delete         → Eliminación masiva
        │   ├─ GET    /export              → Exportar
        │   ├─ GET    /role/{roleId}       → Por rol
        │   └─ POST   /{id}/assign-permissions → Asignar permisos
        │
        ├─ 🎭 Roles (/roles)
        │   ├─ GET    /                    → Listar
        │   ├─ GET    /{id}                → Ver
        │   ├─ POST   /                    → Crear
        │   ├─ PUT    /{id}                → Actualizar
        │   ├─ DELETE /{id}                → Eliminar
        │   ├─ GET    /{role}/permissions  → Permisos del rol
        │   └─ PUT    /{role}/permissions  → Actualizar permisos
        │
        ├─ 🔑 Permisos
        │   ├─ POST   /permissions         → Crear
        │   └─ [User Permissions] /userpermissions
        │       ├─ GET    /                → Listar
        │       ├─ POST   /                → Crear
        │       ├─ PUT    /{id}            → Actualizar
        │       ├─ DELETE /{id}            → Eliminar
        │       └─ GET    /{user_id}       → Por usuario
        │
        ├─ 📦 Módulos (/modules)
        │   ├─ GET    /                    → Listar
        │   ├─ POST   /                    → Crear
        │   ├─ GET    /{id}                → Ver
        │   ├─ PUT    /{id}                → Actualizar
        │   ├─ DELETE /{id}                → Eliminar
        │   └─ [Vistas] /{moduleId}/views
        │       ├─ GET    /                → Listar
        │       ├─ POST   /                → Crear
        │       ├─ GET    /{viewId}        → Ver
        │       ├─ PUT    /{viewId}        → Actualizar
        │       ├─ DELETE /{viewId}        → Eliminar
        │       └─ PUT    /views-order     → Ordenar
        │
        └─ ✅ Flujos de Aprobación (/approval-flows)
            ├─ GET    /                    → Listar
            ├─ POST   /                    → Crear
            ├─ GET    /{flow}              → Ver
            ├─ PUT    /{flow}              → Actualizar
            ├─ DELETE /{flow}              → Eliminar
            ├─ POST   /{flow}/toggle       → Activar/desactivar
            └─ [Etapas] Stages
                ├─ POST   /{flow}/stages   → Crear etapa
                ├─ PUT    /stages/{stage}  → Actualizar etapa
                └─ DELETE /stages/{stage}  → Eliminar etapa

📦 RECURSOS ADICIONALES (Líneas 592-604)
│
├─ 📅 Periodos (apiResource)
│   └─ /periodos + /periodos.inscripciones (nested)
│
├─ 📧 Contactos Enviados (apiResource)
│   ├─ /contactos-enviados
│   ├─ GET /prospectos/{prospecto}/contactos-enviados
│   ├─ GET /contactos-enviados/{id}/download-contrato
│   └─ GET /contactos-enviados/today
│
├─ 🌍 Ubicación
│   └─ GET /ubicacion/{paisId}
│
├─ 🤝 Convenios (apiResource)
│   └─ /convenios
│
├─ 💲 Precios
│   ├─ GET /precios/programa/{programa}
│   └─ GET /precios/convenio/{convenio}/{programa}
│
└─ ⚙️ Reglas (Legacy - apiResource)
    ├─ /rules
    └─ GET /payment-rules-current

═══════════════════════════════════════════════════════════════
ESTADÍSTICAS FINALES
═══════════════════════════════════════════════════════════════
📊 Total de líneas:       604
🎯 Total de rutas:        278
📝 Total de controladores: 61
🔒 Rutas protegidas:      ~230
🌐 Rutas públicas:        ~20
📦 Recursos adicionales:  ~28
═══════════════════════════════════════════════════════════════
```

## Leyenda de Íconos

- 🌐 Rutas Públicas
- 🔒 Rutas Protegidas (auth:sanctum)
- 👥 Prospectos y Seguimiento
- 🎓 Académico
- 💰 Financiero
- ⚙️ Administración
- 📦 Recursos Adicionales

## Navegación Rápida

Para encontrar rutas rápidamente en el archivo:

| Líneas | Sección |
|--------|---------|
| 1-67 | Imports |
| 68-139 | Rutas Públicas |
| 140-177 | Middleware Setup |
| 178-297 | PROSPECTOS Y SEGUIMIENTO |
| 298-371 | ACADÉMICO |
| 372-495 | FINANCIERO |
| 496-591 | ADMINISTRACIÓN |
| 592-604 | Recursos Adicionales |

## Verificación Rápida

```bash
# Contar rutas por dominio
grep -A 120 "DOMINIO: PROSPECTOS" routes/api.php | grep "Route::" | wc -l
grep -A 74 "DOMINIO: ACADÉMICO" routes/api.php | grep "Route::" | wc -l
grep -A 124 "DOMINIO: FINANCIERO" routes/api.php | grep "Route::" | wc -l
grep -A 96 "DOMINIO: ADMINISTRACIÓN" routes/api.php | grep "Route::" | wc -l
```

## Conclusión

Esta estructura visual facilita:
- ✅ **Navegación rápida** por el archivo
- ✅ **Comprensión inmediata** de la organización
- ✅ **Identificación fácil** de rutas por dominio
- ✅ **Mantenimiento simplificado** del código
