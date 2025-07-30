# Moodle Course Queries

Esta sección explica cómo consumir las consultas de cursos de Moodle disponibles en la API. Los datos provienen de la segunda conexión de base de datos configurada como `moodle` en `config/database.php`.

## Endpoints

| Método | Ruta                                           | Descripción |
| ------ | ---------------------------------------------- | ----------- |
| GET    | `/api/moodle/consultas/{carnet?}`             | Lista todos los cursos en los que el usuario está inscrito. Si no se pasa el parámetro en la URL se puede enviar usando `?carnet=ASM12345`. |
| GET    | `/api/moodle/consultas/aprobados/{carnet?}`   | Devuelve únicamente los cursos aprobados o marcados como completados. |
| GET    | `/api/moodle/consultas/reprobados/{carnet?}`  | Devuelve solo los cursos reprobados. |

El parámetro `carnet` debe enviarse con el mismo formato utilizado en Moodle (por ejemplo `asm2020150`). El servicio normaliza el valor, por lo que puede usarse mayúsculas o minúsculas.

## Respuesta esperada

Cada endpoint retorna un objeto JSON con el arreglo `data` dentro del cual se listan los cursos. Un ejemplo de respuesta simplificada es el siguiente:

```json
{
  "data": [
    {
      "userid": 15,
      "carnet": "asm2020150",
      "fullname": "Juan Pérez",
      "courseid": 42,
      "coursename": "Gestión del tiempo, presupuestos y costos en los proyectos",
      "fecha_inicio_curso": "2025-02-10 08:00:00",
      "fecha_fin_curso": "2025-03-20 18:00:00",
      "finalgrade": 90.5,
      "estado_curso": "Aprobado"
    }
  ]
}
```

El campo `coursename` se limpia automáticamente para remover información de la agenda (mes, día, año o siglas de programa). Así, un nombre como:

```
Febrero Jueves 2025 MDGP Gestión del tiempo, presupuestos y costos en los proyectos
```

se convierte en:

```
Gestión del tiempo, presupuestos y costos en los proyectos
```

## Cursos de módulos

En la documentación y en el código se habla de *cursos de módulos* para referirse a todo curso que proviene de esta segunda base de datos y de las consultas almacenadas aquí. Todas las rutas anteriores se alimentan de esas consultas.

