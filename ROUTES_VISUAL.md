# API Routes Structure - Visual Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              routes/api.php                                  │
│                           (Main Entry Point)                                 │
│                                                                              │
│  Loads modular route files by domain                                        │
│  + Legacy compatibility routes (health checks)                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     │ requires
                                     ↓
        ┌────────────────────────────────────────────────────────┐
        │                                                         │
        ↓                    ↓                 ↓                  ↓
┌──────────────┐    ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│   PUBLIC     │    │  PROSPECTOS  │   │ SEGUIMIENTO  │   │  ACADÉMICO   │
│              │    │              │   │              │   │              │
│  98 lines    │    │  105 lines   │   │   61 lines   │   │  156 lines   │
│              │    │              │   │              │   │              │
│ No Auth ❌   │    │ Auth ✅       │   │ Auth ✅       │   │ Mixed Auth   │
└──────────────┘    └──────────────┘   └──────────────┘   └──────────────┘
        │                    │                 │                  │
        ↓                    ↓                 ↓                  ↓
┌──────────────┐    ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│  FINANCIERO  │    │ ADMINISTRA-  │   │              │   │              │
│              │    │    CIÓN      │   │              │   │              │
│  186 lines   │    │  132 lines   │   │              │   │              │
│              │    │              │   │              │   │              │
│ Auth ✅       │    │ Auth ✅       │   │              │   │              │
└──────────────┘    └──────────────┘   └──────────────┘   └──────────────┘

TOTAL: 821 lines (vs 719 in original single file)
```

## Domain Breakdown

### 🌐 PUBLIC (routes/api/public.php)
```
┌──────────────────────────────────────┐
│ • Health Check (/health)            │
│ • Login (/login)                    │
│ • Inscriptions & Payment Plans      │
│ • Public Prospect Info              │
│ • Email Sending                     │
│ • Admin Reports (public endpoints)  │
└──────────────────────────────────────┘
```

### 👥 PROSPECTOS (routes/api/prospectos.php)
```
┌──────────────────────────────────────┐
│ • CRUD Operations                   │
│ • Bulk Operations                   │
│ • Status Filtering                  │
│ • Documents Management              │
│ • Import/Export                     │
│ • Column Configuration              │
│ • Contacts & Communication          │
│ • Duplicate Detection               │
└──────────────────────────────────────┘
```

### 📅 SEGUIMIENTO (routes/api/seguimiento.php)
```
┌──────────────────────────────────────┐
│ • Citas (Appointments)              │
│ • Interacciones (Interactions)      │
│ • Tareas (Tasks)                    │
│ • Actividades (Activities)          │
└──────────────────────────────────────┘
```

### 🎓 ACADÉMICO (routes/api/academico.php)
```
┌──────────────────────────────────────┐
│ • Programs (Public)                 │
│ • Locations & Prices (Public)       │
│ • Convenios (Public)                │
│ • Enrollment Periods (Public)       │
│ ───────────────────────────────────  │
│ • Courses (Protected)               │
│ • Student Programs (Protected)      │
│ • Student Import (Protected)        │
│ • Rankings (Protected)              │
│ • Moodle Integration (Protected)    │
│ • Approval Flows (Protected)        │
└──────────────────────────────────────┘
```

### 💰 FINANCIERO (routes/api/financiero.php)
```
┌──────────────────────────────────────┐
│ • Dashboard                         │
│ • Invoices                          │
│ • Payments                          │
│ • Payment Rules & Notifications     │
│ • Blocking Rules                    │
│ • Payment Gateways                  │
│ • Exception Categories              │
│ • Financial Reports                 │
│ • Reconciliation (EN & ES)          │
│ • Collection Logs                   │
│ • Collections Management            │
│ • Kardex & Installments             │
│ • Student Payment Portal            │
└──────────────────────────────────────┘
```

### 🔐 ADMINISTRACIÓN (routes/api/administracion.php)
```
┌──────────────────────────────────────┐
│ • Current User & Logout             │
│ • Users Management                  │
│ • Roles Management                  │
│ • Permissions                       │
│ • Modules & Views                   │
│ • User Permissions                  │
│ • Sessions Management               │
│ • Commissions                       │
└──────────────────────────────────────┘
```

## Route Flow Example

### Creating a Prospect with Documents

```
Frontend Request
      │
      ↓
┌──────────────┐
│ POST         │
│ /prospectos  │ ──→ routes/api.php ──→ routes/api/prospectos.php
└──────────────┘                              │
                                              ↓
                                    ProspectoController::store()
                                              │
                                              ↓
                                         Database
```

### Student Payment Flow

```
Frontend Request
      │
      ↓
┌─────────────────────────────┐
│ GET                         │
│ /estudiante/pagos/pendientes│ ──→ routes/api.php ──→ routes/api/financiero.php
└─────────────────────────────┘                              │
                                                             ↓
                                              EstudiantePagosController::pagosPendientes()
                                                             │
                                                             ↓
                                                        Database
```

## Authentication Flow

```
┌─────────────┐
│ POST /login │ ──→ routes/api/public.php (No Auth)
└─────────────┘              │
                             ↓
                  LoginController::login()
                             │
                             ↓
                   Returns JWT/Sanctum Token
                             │
      ┌──────────────────────┴──────────────────────┐
      │                                              │
      ↓                                              ↓
┌─────────────┐                              ┌─────────────┐
│ Protected   │                              │ Protected   │
│ Routes in:  │                              │ Routes in:  │
│             │                              │             │
│ • prospectos│                              │ • financiero│
│ • academico │                              │ • admin     │
│ • seguimiento│                             │             │
└─────────────┘                              └─────────────┘
```

## Benefits Visualization

### Before (Single File)
```
┌────────────────────────────────┐
│      routes/api.php            │
│                                │
│  ┌──────────────────────────┐ │
│  │ 719 lines of mixed       │ │
│  │ routes, hard to navigate │ │
│  │ • Public routes          │ │
│  │ • Protected routes       │ │
│  │ • Duplicates             │ │
│  │ • Inconsistent naming    │ │
│  └──────────────────────────┘ │
└────────────────────────────────┘
```

### After (Modular)
```
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐
│ Public   │  │Prospectos│  │Seguimien-│  │Académico │
│ 98 lines │  │105 lines │  │to 61 lin.│  │156 lines │
└──────────┘  └──────────┘  └──────────┘  └──────────┘

┌──────────┐  ┌──────────┐
│Financiero│  │Administra│
│186 lines │  │ción 132  │
└──────────┘  └──────────┘

✅ Easy to navigate
✅ Clear organization
✅ Maintainable
✅ Documented
```

## Statistics

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Total Files | 1 | 7 | +6 📁 |
| Total Lines | 719 | 821 | +102 lines |
| Avg Lines/File | 719 | 117 | -602 lines |
| Documentation | Minimal | Comprehensive | ⬆️⬆️⬆️ |
| Organization | Poor | Excellent | ⬆️⬆️⬆️ |
| Maintainability | Low | High | ⬆️⬆️⬆️ |

## Summary

✅ **Modular**: 6 domain-specific files
✅ **Organized**: Clear separation by business domain  
✅ **Documented**: Inline comments and comprehensive docs
✅ **Compatible**: 100% backward compatible
✅ **Maintainable**: Easy to find and modify routes
✅ **Scalable**: Easy to add new routes
