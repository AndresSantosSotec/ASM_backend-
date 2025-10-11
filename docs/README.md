# 📚 Documentación del Proyecto ASM Backend

## Bienvenido

Este directorio contiene toda la documentación organizada del proyecto ASM Backend.

---

## 📂 Estructura de Documentación

### 🎓 Módulos del Sistema

#### [Reportes de Matrícula](./reportes-matricula/)
Sistema completo de reportes de matrícula y alumnos nuevos con capacidades avanzadas de filtrado, comparación y exportación.

**Características principales:**
- Consulta de reportes con filtros múltiples
- Comparación de períodos automática
- Exportación en PDF, Excel y CSV
- Tendencias y proyecciones
- Métricas calculadas automáticamente

📖 [Ver documentación completa →](./reportes-matricula/README.md)

---

## 🚀 Guías de Inicio Rápido

### Para Desarrolladores Frontend

```javascript
// Consumir API de reportes de matrícula
const response = await fetch('/api/administracion/reportes-matricula', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const data = await response.json();
console.log(data.periodoActual.totales.matriculados);
```

📖 Más información: [API Docs](./reportes-matricula/REPORTES_MATRICULA_API_DOCS.md)

### Para Desarrolladores Backend

```bash
# Ejecutar tests
php artisan test

# Ver rutas disponibles
php artisan route:list

# Limpiar caché
php artisan cache:clear
```

---

## 📋 Documentación por Categoría

### API Reference
- [Reportes de Matrícula API](./reportes-matricula/REPORTES_MATRICULA_API_DOCS.md) - Documentación completa de endpoints

### Guías de Implementación
- [Reportes de Matrícula - Guía Rápida](./reportes-matricula/REPORTES_MATRICULA_GUIA_RAPIDA.md) - Implementación paso a paso

### Resúmenes Ejecutivos
- [Reportes de Matrícula - Resumen](./reportes-matricula/REPORTES_MATRICULA_RESUMEN_IMPLEMENTACION.md) - Visión general del proyecto

---

## 🛠️ Stack Tecnológico

- **Framework:** Laravel 10.x
- **PHP:** 8.x
- **Base de Datos:** PostgreSQL / MySQL
- **Autenticación:** Laravel Sanctum
- **Fechas:** Carbon
- **Excel:** Maatwebsite Excel v3.1
- **PDF:** Barryvdh DomPDF v3.1
- **Testing:** PHPUnit

---

## 🔍 Buscar Documentación

### Por Funcionalidad
- **Reportes y Analytics** → [Reportes de Matrícula](./reportes-matricula/)
- **Dashboard Administrativo** → `AdministracionController.php`
- **Exportación de Datos** → Clases Export en `app/Exports/`

### Por Tipo de Usuario
- **Project Managers** → Ver resúmenes de implementación
- **Developers Frontend** → Ver API docs
- **Developers Backend** → Ver guías rápidas
- **QA Engineers** → Ver archivos de tests

---

## 📊 Estado de Módulos

| Módulo | Estado | Versión | Documentación |
|--------|--------|---------|---------------|
| Reportes de Matrícula | ✅ Completo | 1.0.0 | [Ver docs](./reportes-matricula/) |
| Dashboard Administrativo | ✅ Completo | 1.0.0 | En controller |
| Dashboard de Prospectos | ✅ Completo | 1.0.0 | - |
| Gestión de Usuarios | ✅ Completo | 1.0.0 | - |
| Gestión de Pagos | ✅ Completo | 1.0.0 | - |

---

## 🤝 Contribuir

Para agregar nueva documentación:

1. Crea un directorio para el módulo: `docs/nombre-modulo/`
2. Agrega los archivos de documentación
3. Crea un `README.md` en el directorio del módulo
4. Actualiza este índice principal
5. Actualiza `.gitignore` si es necesario

### Estructura Recomendada

```
docs/
├── README.md                          # Este archivo
└── nombre-modulo/
    ├── README.md                      # Índice del módulo
    ├── NOMBRE_MODULO_API_DOCS.md      # Documentación API
    ├── NOMBRE_MODULO_GUIA_RAPIDA.md   # Guía rápida
    └── NOMBRE_MODULO_RESUMEN.md       # Resumen ejecutivo
```

---

## 📝 Convenciones

### Nomenclatura de Archivos
- **API Docs:** `NOMBRE_MODULO_API_DOCS.md`
- **Guía Rápida:** `NOMBRE_MODULO_GUIA_RAPIDA.md`
- **Resumen:** `NOMBRE_MODULO_RESUMEN_IMPLEMENTACION.md`
- **Índice del Módulo:** `README.md`

### Formato de Documentación
- Usar markdown para todos los documentos
- Incluir ejemplos de código
- Agregar tabla de contenidos en documentos largos
- Usar emojis para mejor legibilidad 📚 ✅ 🚀
- Incluir fecha y versión en cada documento

---

## 🔗 Enlaces Externos

- **Repositorio GitHub:** [AndresSantosSotec/ASM_backend-](https://github.com/AndresSantosSotec/ASM_backend-)
- **Laravel:** [https://laravel.com/docs](https://laravel.com/docs)
- **PHP:** [https://www.php.net/docs.php](https://www.php.net/docs.php)
- **PostgreSQL:** [https://www.postgresql.org/docs/](https://www.postgresql.org/docs/)

---

## 📞 Contacto y Soporte

Para dudas sobre la documentación o el proyecto:

1. Revisa la documentación del módulo correspondiente
2. Consulta los archivos de código fuente
3. Ejecuta los tests para verificar funcionalidad
4. Revisa los logs del sistema

---

## 📅 Historial de Actualizaciones

| Fecha | Versión | Descripción |
|-------|---------|-------------|
| 2025-10-11 | 1.0.0 | Creación de estructura de documentación organizada |
| 2025-10-11 | 1.0.0 | Migración de docs de Reportes de Matrícula |

---

**Última actualización:** 11 de Octubre, 2025  
**Mantenido por:** Equipo de Desarrollo ASM Backend

**© 2025 - ASM Backend - Todos los derechos reservados**
