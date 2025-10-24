# Diagrama de Arquitectura - Sistemas de Permisos Separados

```
╔═══════════════════════════════════════════════════════════════════════════════╗
║                    SISTEMA DE PERMISOS - ARQUITECTURA SEPARADA                ║
╚═══════════════════════════════════════════════════════════════════════════════╝

┌─────────────────────────────────────────────────────────────────────────────┐
│                        PERMISOS POR USUARIO (Nuevo)                         │
│                   "¿Qué VISTAS puede acceder cada usuario?"                 │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────┐
    │  users   │
    │    id    │──┐
    └──────────┘  │
                  │ user_id
                  │
    ┌─────────────▼──────────────┐
    │   userpermissions (pivot)  │
    │   user_id                  │
    │   permission_id ────────┐  │
    │   assigned_at           │  │
    │   scope                 │  │
    └─────────────────────────┘  │
                                 │ permission_id
                                 │
                  ┌──────────────▼──────────────┐
                  │   permisos (NUEVA TABLA)    │
                  │   id                        │
                  │   moduleview_id ────────┐   │
                  │   action (view)         │   │
                  │   name                  │   │
                  │   description           │   │
                  └─────────────────────────┘   │
                                                │ moduleview_id
                                                │
                            ┌───────────────────▼───────────────┐
                            │       moduleviews                 │
                            │       id                          │
                            │       module_id ────────┐         │
                            │       menu              │         │
                            │       submenu           │         │
                            │       view_path         │         │
                            └─────────────────────────┘         │
                                                                │ module_id
                                                                │
                                          ┌─────────────────────▼─────────┐
                                          │        modules                │
                                          │        id                     │
                                          │        name                   │
                                          │        description            │
                                          └───────────────────────────────┘

    Controlador: UserPermisosController
    Modelo: Permisos (usa tabla 'permisos')
    Endpoints:
      GET    /api/userpermissions?user_id={id}
      POST   /api/userpermissions
      DELETE /api/userpermissions/{id}


┌─────────────────────────────────────────────────────────────────────────────┐
│                      PERMISOS POR ROL (Existente - NO TOCAR)                │
│           "¿Qué ACCIONES puede realizar cada rol en cada vista?"            │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────┐
    │  roles   │
    │    id    │──┐
    └──────────┘  │
                  │ role_id
                  │
    ┌─────────────▼──────────────┐
    │   rolepermissions (pivot)  │
    │   role_id                  │
    │   permission_id ────────┐  │
    │   scope                 │  │
    │   assigned_at           │  │
    └─────────────────────────┘  │
                                 │ permission_id
                                 │
                  ┌──────────────▼──────────────────┐
                  │   permissions (TABLA ORIGINAL)  │
                  │   id                            │
                  │   moduleview_id ────────┐       │
                  │   action (view, create, │       │
                  │          edit, delete,  │       │
                  │          export)        │       │
                  │   name                  │       │
                  │   description           │       │
                  │   is_enabled            │       │
                  └─────────────────────────┘       │
                                                    │ moduleview_id
                                                    │
                            ┌───────────────────────▼───────────────┐
                            │       moduleviews                     │
                            │       (misma tabla que arriba)        │
                            └───────────────────────────────────────┘

    Controlador: RolePermissionController
    Modelo: Permission (usa tabla 'permissions')
    Endpoints:
      GET /api/roles/{role}/permissions
      PUT /api/roles/{role}/permissions


┌─────────────────────────────────────────────────────────────────────────────┐
│                     PERMISOS EFECTIVOS (Combinación)                        │
│          "Combina acceso a vistas (usuario) + acciones (rol)"               │
└─────────────────────────────────────────────────────────────────────────────┘

    Servicio: EffectivePermissionsService

    Flujo:
    1. Usuario → userpermissions → permisos (action='view')
       └─> ¿Tiene acceso a la vista X?
    
    2. Si SÍ → Usuario → roles → rolepermissions → permissions
       └─> ¿Qué puede hacer en vista X? (create, edit, delete, export)
    
    3. Si NO → Bloqueado (403)

    Resultado:
    {
      "/finanzas/conciliacion": {
        "view": true,     ← De permisos (usuario)
        "create": true,   ← De permissions (rol)
        "edit": true,     ← De permissions (rol)
        "delete": false,  ← De permissions (rol)
        "export": true    ← De permissions (rol)
      }
    }


┌─────────────────────────────────────────────────────────────────────────────┐
│                          REGLAS DE SEPARACIÓN                                │
└─────────────────────────────────────────────────────────────────────────────┘

    ✅ CORRECTO:
    • UserPermisosController → Permisos → tabla permisos
    • RolePermissionController → Permission → tabla permissions
    • EffectivePermissionsService → ambas tablas (separadas correctamente)

    ❌ PROHIBIDO:
    • UserPermisosController usando tabla permissions
    • RolePermissionController usando Permisos
    • Mezclar consultas entre permissions y permisos
    • Role.permissions() usando Permisos
    • Hacer JOIN entre rolepermissions y permisos


┌─────────────────────────────────────────────────────────────────────────────┐
│                          COMANDOS ARTISAN                                    │
└─────────────────────────────────────────────────────────────────────────────┘

    # Sincronizar permisos de usuario con moduleviews
    php artisan permissions:sync --action=view
    └─> Crea registros en tabla 'permisos'

    # Corregir nombres de permisos de usuario
    php artisan permissions:fix-names
    └─> Actualiza tabla 'permisos'

    # Verificar separación
    php verify-permission-separation.php
    └─> Valida que todo está correctamente separado


┌─────────────────────────────────────────────────────────────────────────────┐
│                          MIGRACIONES                                         │
└─────────────────────────────────────────────────────────────────────────────┘

    Paso 1: Crear tabla permisos
    2025_10_17_000000_create_permisos_table.php
    └─> CREATE TABLE permisos (...)

    Paso 2: Migrar datos
    2025_10_17_000001_migrate_user_permissions_to_permisos.php
    └─> INSERT INTO permisos SELECT ... FROM permissions WHERE ...
    └─> UPDATE userpermissions SET permission_id = nuevo_id

    Resultado:
    • permissions: Intacta, solo para roles
    • permisos: Nueva, con datos de usuario
    • userpermissions: Actualizada, apunta a permisos


┌─────────────────────────────────────────────────────────────────────────────┐
│                    EJEMPLO DE DATOS                                          │
└─────────────────────────────────────────────────────────────────────────────┘

    Tabla: permisos (para usuarios)
    ┌────┬───────────────┬────────┬──────────────────────────────┬─────────────┐
    │ id │ moduleview_id │ action │ name                         │ description │
    ├────┼───────────────┼────────┼──────────────────────────────┼─────────────┤
    │  1 │            10 │ view   │ view:/finanzas/conciliacion  │ Vista acc.. │
    │  2 │            11 │ view   │ view:/finanzas/seguimiento   │ Vista seg.. │
    │  3 │            12 │ view   │ view:/finanzas/reportes      │ Vista rep.. │
    └────┴───────────────┴────────┴──────────────────────────────┴─────────────┘

    Tabla: permissions (para roles)
    ┌────┬───────────────┬────────┬──────────────────────────────┬────────────┐
    │ id │ moduleview_id │ action │ name                         │ is_enabled │
    ├────┼───────────────┼────────┼──────────────────────────────┼────────────┤
    │  1 │            10 │ view   │ view:/finanzas/conciliacion  │ true       │
    │  2 │            10 │ create │ create:/finanzas/concilia... │ true       │
    │  3 │            10 │ edit   │ edit:/finanzas/conciliacion  │ true       │
    │  4 │            10 │ delete │ delete:/finanzas/concilia... │ true       │
    │  5 │            10 │ export │ export:/finanzas/concilia... │ true       │
    └────┴───────────────┴────────┴──────────────────────────────┴────────────┘


┌─────────────────────────────────────────────────────────────────────────────┐
│                    CASOS DE USO                                              │
└─────────────────────────────────────────────────────────────────────────────┘

    Caso 1: Asignar vistas a un usuario
    POST /api/userpermissions
    {
      "user_id": 5,
      "permissions": [10, 11, 12]  ← IDs de moduleviews
    }
    └─> Busca/crea en 'permisos' con action='view'
    └─> Inserta en 'userpermissions'

    Caso 2: Ver permisos de un usuario
    GET /api/userpermissions?user_id=5
    └─> SELECT * FROM userpermissions 
        JOIN permisos ON permisos.id = userpermissions.permission_id
        JOIN moduleviews ON moduleviews.id = permisos.moduleview_id

    Caso 3: Configurar permisos de un rol
    PUT /api/roles/2/permissions
    {
      "permissions": [
        {"moduleview_id": 10, "actions": ["view", "create", "edit"]}
      ]
    }
    └─> SELECT * FROM permissions WHERE moduleview_id = 10 AND action IN (...)
    └─> INSERT INTO rolepermissions (role_id, permission_id)

    Caso 4: Verificar si usuario puede hacer acción
    EffectivePermissionsService::forUser($user)
    1. ¿Tiene acceso a la vista? → permisos (view)
    2. ¿Qué puede hacer? → permissions (según rol)
    └─> Devuelve mapa de permisos efectivos


╔═══════════════════════════════════════════════════════════════════════════════╗
║                              RESUMEN FINAL                                    ║
╚═══════════════════════════════════════════════════════════════════════════════╝

    ✅ Dos sistemas completamente separados
    ✅ Dos tablas independientes (permisos y permissions)
    ✅ Dos modelos diferentes (Permisos y Permission)
    ✅ Controladores especializados
    ✅ Migraciones para separar datos
    ✅ Documentación completa
    ✅ Script de verificación

    Sistema de Usuario:    users → userpermissions → permisos → moduleviews
    Sistema de Rol:        roles → rolepermissions → permissions → moduleviews
    
    NO SE MEZCLAN - COMPLETAMENTE INDEPENDIENTES
```
