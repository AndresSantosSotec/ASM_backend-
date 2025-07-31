# Guía de Módulos

Esta guía describe brevemente cada módulo principal de la API y las rutas básicas disponibles. Para los detalles completos consulta `routes/api.php`.

## Prospectos
- **Prefijo**: `/api/prospectos`
- CRUD completo y operaciones masivas (`bulkAssign`, `bulkUpdateStatus`, `bulkDelete`).
- Endpoints adicionales para enviar contratos y listar prospectos pendientes de aprobación.

## Programas
- **Prefijo**: `/api/programas`
- Crear, actualizar y eliminar programas académicos.
- Manejo de precios de programa mediante rutas anidadas.

## Usuarios y Roles
- **Prefijos**: `/api/users` y `/api/roles`
- Permite administración de usuarios (incluye restaurar, exportar y operaciones en lote) y la gestión de roles del sistema.

## Módulos y Vistas
- **Prefijo**: `/api/modules`
- Registro de módulos de la aplicación y sus vistas internas.
- Soporta reordenamiento de vistas con `updateOrder`.

## Sesiones, Citas e Interacciones
- **Prefijos**: `/api/sessions`, `/api/citas`, `/api/interacciones`
- Gestión de sesiones activas, citas programadas y registro de interacciones con prospectos.

## Finanzas
- **Rutas**: `/api/invoices`, `/api/payments`, `/api/payment-plans`, `/api/reconciliation`
- Control de facturas, pagos, planes de pago e importación de conciliaciones bancarias.

## Cursos y Moodle
- **Prefijo**: `/api/courses` y `/api/moodle/consultas`
- Creación y mantenimiento de cursos propios.
- Consultas de cursos de Moodle (aprobados, reprobados, etc.) mediante una base de datos secundaria.

