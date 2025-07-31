# Visión General de la API

Este proyecto utiliza **Laravel** para exponer una API REST que gestiona prospectos, programas, usuarios y procesos financieros. Las rutas principales se definen en `routes/api.php` y cada endpoint se implementa en los controladores ubicados en `app/Http/Controllers/Api`.

Si sólo necesitas un vistazo rápido de las rutas disponibles, consulta también `docs/ModuleGuide.md`.

## Autenticación

La autenticación se basa en tokens de Laravel Sanctum. Los puntos de entrada son:

- `POST /api/login` → `LoginController@login`
- `POST /api/logout` → `LoginController@logout` (requiere token)

El middleware `auth:sanctum` protege los grupos de rutas que sólo pueden ser usados por usuarios autenticados.

## Gestión de Prospectos

El controlador `ProspectoController` contiene las funciones clave para manejar el ciclo de vida del prospecto:

- `index` y `show` para listar y consultar prospectos.
- `store`, `update` y `destroy` para operaciones CRUD.
- `bulkAssign` y `bulkUpdateStatus` para operaciones masivas.
- `pendientesAprobacion` para obtener prospectos en espera de aprobación.

Estas rutas están agrupadas bajo el prefijo `/api/prospectos`.

## Programas y Ubicación

`ProgramaController` maneja la creación y edición de programas académicos, junto con la administración de precios. Las rutas se agrupan bajo `/api/programas`. Para consultar ubicaciones se usa `UbicacionController@getUbicacionByPais`.

## Roles y Usuarios

El sistema de permisos utiliza `RolController` y `UserController` para administrar roles y usuarios. Funciones como `restore` o `bulkDelete` permiten recuperar o eliminar múltiples usuarios.

## Módulos y Vistas

`ModulesController` y `ModulesViewsController` permiten registrar módulos de la aplicación y las vistas asociadas. Incluyen funciones para mantener el orden de las vistas (`updateOrder`).

## Sesiones, Citas e Interacciones

Los controladores `SessionController`, `CitasController` e `InteraccionesController` exponen operaciones básicas de consulta, creación y cierre para cada recurso.

## Finanzas

`InvoiceController` y `PaymentController` manejan facturas y pagos. Los planes de pago se administran con `PaymentPlanController` y las conciliaciones bancarias con `ReconciliationController`.

## Integración con Moodle

El proyecto ofrece endpoints para sincronizar cursos de Moodle y realizar consultas de cursos aprobados o reprobados. Las funciones clave se documentan en `docs/MoodleSync.md` y `docs/MoodleCourseQueries.md`.

## Rutas y Controladores Clave

La mayor parte de las rutas está agrupada en `routes/api.php`. Cada grupo utiliza el middleware adecuado y se vincula a métodos de controladores con nombres descriptivos. Consulta ese archivo para ver todas las rutas disponibles. Para un resumen rápido de los módulos revisa también `docs/ModuleGuide.md`.

## Otros Módulos Relevantes

- **Documentos** (`ProspectosDocumentoController`): permite subir, descargar y asociar archivos a un prospecto.
- **Convenios** (`ConvenioController`): CRUD completo de convenios empresariales.
- **Estudiante Programa** (`EstudianteProgramaController`): gestiona la relación entre un prospecto y los programas en los que se inscribe.
- **Duplicados** (`DuplicateRecordController`): herramientas para detectar y actuar sobre registros duplicados.
- **Comisiones** (`CommissionController` y relacionados): configuración global y cálculo de comisiones para asesores.
- **Reglas de Pago** (`RuleController` y `PaymentRuleNotificationController`): define reglas automáticas y notificaciones de pago.

