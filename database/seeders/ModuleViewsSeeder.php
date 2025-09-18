<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ModulesViews;

class ModuleViewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $moduleViews = [
            // Módulo Prospectos Y Asesores (module_id = 2)
            [
                'id' => 1,
                'module_id' => 2,
                'menu' => 'Gestión de Prospectos',
                'submenu' => 'Gestión de Prospectos',
                'view_path' => '/gestion',
                'status' => true,
                'order_num' => 1,
                'icon' => 'Users'
            ],
            [
                'id' => 2,
                'module_id' => 2,
                'menu' => 'Captura de Prospectos',
                'submenu' => 'Captura de Prospectos',
                'view_path' => '/captura',
                'status' => true,
                'order_num' => 2,
                'icon' => 'Plus'
            ],
            [
                'id' => 3,
                'module_id' => 2,
                'menu' => 'Leads Asignados',
                'submenu' => 'Leads Asignados',
                'view_path' => '/leads-asignados',
                'status' => true,
                'order_num' => 3,
                'icon' => 'FileText'
            ],
            [
                'id' => 4,
                'module_id' => 2,
                'menu' => 'Panel de Seguimiento',
                'submenu' => 'Panel de Seguimiento',
                'view_path' => '/seguimiento',
                'status' => true,
                'order_num' => 4,
                'icon' => 'FileText'
            ],
            [
                'id' => 5,
                'module_id' => 2,
                'menu' => 'Importar Leads',
                'submenu' => 'Importar Leads',
                'view_path' => '/importar-leads',
                'status' => true,
                'order_num' => 5,
                'icon' => 'ClipboardList'
            ],
            [
                'id' => 8,
                'module_id' => 2,
                'menu' => 'Correos',
                'submenu' => 'Correos',
                'view_path' => '/correos',
                'status' => true,
                'order_num' => 8,
                'icon' => 'ClipboardList'
            ],
            [
                'id' => 9,
                'module_id' => 2,
                'menu' => 'Calendario',
                'submenu' => 'Calendario',
                'view_path' => '/calendario',
                'status' => true,
                'order_num' => 9,
                'icon' => 'Activities'
            ],
            [
                'id' => 12,
                'module_id' => 2,
                'menu' => 'Gestión de Leads',
                'submenu' => 'Gestión de Leads',
                'view_path' => '/admin',
                'status' => true,
                'order_num' => 12,
                'icon' => 'Users'
            ],

            // Módulo Inscripción (module_id = 3)
            [
                'id' => 19,
                'module_id' => 3,
                'menu' => 'Ficha de Inscripción',
                'submenu' => 'Ficha de Inscripción',
                'view_path' => '/inscripcion/ficha',
                'status' => true,
                'order_num' => 1,
                'icon' => 'FileText'
            ],
            [
                'id' => 20,
                'module_id' => 3,
                'menu' => 'Revisión de Fichas',
                'submenu' => 'Revisión de Fichas',
                'view_path' => '/inscripcion/revision',
                'status' => true,
                'order_num' => 2,
                'icon' => 'FileText'
            ],
            [
                'id' => 21,
                'module_id' => 3,
                'menu' => 'Firma Digital',
                'submenu' => 'Firma Digital',
                'view_path' => '/firma',
                'status' => true,
                'order_num' => 3,
                'icon' => 'FileSignature'
            ],
            [
                'id' => 22,
                'module_id' => 3,
                'menu' => 'Documentos',
                'submenu' => 'Validación de Documentos',
                'view_path' => '/documentos',
                'status' => true,
                'order_num' => 4,
                'icon' => 'FileText'
            ],
            [
                'id' => 25,
                'module_id' => 3,
                'menu' => 'Administración',
                'submenu' => 'Periodos de Inscripción',
                'view_path' => '/inscripcion/admin/periodos',
                'status' => true,
                'order_num' => 7,
                'icon' => 'Calendar'
            ],
            [
                'id' => 26,
                'module_id' => 3,
                'menu' => 'Administración',
                'submenu' => 'Flujos de Aprobación',
                'view_path' => '/inscripcion/admin/flujos',
                'status' => true,
                'order_num' => 8,
                'icon' => 'Activity'
            ],
            [
                'id' => 92,
                'module_id' => 3,
                'menu' => 'Corrección de Documentos',
                'submenu' => 'Corrección de Documentos',
                'view_path' => '/inscripcion/Correcion Docs',
                'status' => true,
                'order_num' => 22,
                'icon' => 'FileText'
            ],

            // Módulo Académico (module_id = 4)
            [
                'id' => 27,
                'module_id' => 4,
                'menu' => 'Programas Académicos',
                'submenu' => 'Programas Académicos',
                'view_path' => '/academico/programas',
                'status' => true,
                'order_num' => 1,
                'icon' => 'BookOpen'
            ],
            [
                'id' => 28,
                'module_id' => 4,
                'menu' => 'Gestión de Usuarios',
                'submenu' => 'Gestión de Usuarios',
                'view_path' => '/academico/usuarios',
                'status' => true,
                'order_num' => 2,
                'icon' => 'Users'
            ],
            [
                'id' => 90,
                'module_id' => 4,
                'menu' => 'Academico',
                'submenu' => 'Moodle',
                'view_path' => '/academico/moodle',
                'status' => true,
                'order_num' => 2,
                'icon' => 'FileText'
            ],
            [
                'id' => 30,
                'module_id' => 4,
                'menu' => 'Asignación de Cursos',
                'submenu' => 'Asignación de Cursos',
                'view_path' => '/academico/asignacion',
                'status' => true,
                'order_num' => 4,
                'icon' => 'ClipboardList'
            ],
            [
                'id' => 31,
                'module_id' => 4,
                'menu' => 'Estatus Académico',
                'submenu' => 'Estatus Académico',
                'view_path' => '/academico/estatus-alumno',
                'status' => true,
                'order_num' => 5,
                'icon' => 'UserCheck'
            ],
            [
                'id' => 33,
                'module_id' => 4,
                'menu' => 'Ranking Académico',
                'submenu' => 'Ranking Académico',
                'view_path' => '/academico/ranking',
                'status' => true,
                'order_num' => 7,
                'icon' => 'BarChart2'
            ],
            [
                'id' => 91,
                'module_id' => 4,
                'menu' => 'Migrar Estudiantes',
                'submenu' => 'Migrar Estudiantes',
                'view_path' => '/academico/migrar-estudiantes',
                'status' => true,
                'order_num' => 9,
                'icon' => 'Database'
            ],

            // Módulo Docentes (module_id = 5)
            [
                'id' => 34,
                'module_id' => 5,
                'menu' => 'Portal Docente',
                'submenu' => 'Portal Docente',
                'view_path' => '/docente',
                'status' => true,
                'order_num' => 1,
                'icon' => 'LayoutDashboard'
            ],
            [
                'id' => 35,
                'module_id' => 5,
                'menu' => 'Mis Cursos',
                'submenu' => 'Mis Cursos',
                'view_path' => '/docente/cursos',
                'status' => true,
                'order_num' => 2,
                'icon' => 'BookOpen'
            ],
            [
                'id' => 36,
                'module_id' => 5,
                'menu' => 'Alumnos',
                'submenu' => 'Alumnos',
                'view_path' => '/docente/alumnos',
                'status' => true,
                'order_num' => 3,
                'icon' => 'Users'
            ],
            [
                'id' => 37,
                'module_id' => 5,
                'menu' => 'Material Didáctico',
                'submenu' => 'Material Didáctico',
                'view_path' => '/docente/material',
                'status' => true,
                'order_num' => 4,
                'icon' => 'FileText'
            ],
            [
                'id' => 38,
                'module_id' => 5,
                'menu' => 'Mensajería e Invitaciones',
                'submenu' => 'Mensajería e Invitaciones',
                'view_path' => '/docente/mensajes',
                'status' => true,
                'order_num' => 5,
                'icon' => 'Mail'
            ],
            [
                'id' => 39,
                'module_id' => 5,
                'menu' => 'Medallero e Insignias',
                'submenu' => 'Medallero e Insignias',
                'view_path' => '/docente/medallero',
                'status' => true,
                'order_num' => 6,
                'icon' => 'Medal'
            ],
            [
                'id' => 40,
                'module_id' => 5,
                'menu' => 'Mi Aprendizaje',
                'submenu' => 'Mi Aprendizaje',
                'view_path' => '/docente/mi-aprendizaje',
                'status' => true,
                'order_num' => 7,
                'icon' => 'BookOpen'
            ],
            [
                'id' => 41,
                'module_id' => 5,
                'menu' => 'Calendario',
                'submenu' => 'Calendario',
                'view_path' => '/docente/calendario',
                'status' => true,
                'order_num' => 8,
                'icon' => 'Calendar'
            ],
            [
                'id' => 42,
                'module_id' => 5,
                'menu' => 'Notificaciones',
                'submenu' => 'Notificaciones',
                'view_path' => '/docente/notificaciones',
                'status' => true,
                'order_num' => 9,
                'icon' => 'Bell'
            ],
            [
                'id' => 43,
                'module_id' => 5,
                'menu' => 'Certificaciones',
                'submenu' => 'Certificaciones',
                'view_path' => '/docente/certificaciones',
                'status' => true,
                'order_num' => 10,
                'icon' => 'Award'
            ],

            // Módulo Estudiantes (module_id = 6)
            [
                'id' => 44,
                'module_id' => 6,
                'menu' => 'Dashboard Estudiantil',
                'submenu' => 'Dashboard Estudiantil',
                'view_path' => '/estudiantes',
                'status' => true,
                'order_num' => 1,
                'icon' => 'LayoutDashboard'
            ],
            [
                'id' => 45,
                'module_id' => 6,
                'menu' => 'Documentos',
                'submenu' => 'Documentos',
                'view_path' => '/estudiantes/documentos',
                'status' => true,
                'order_num' => 2,
                'icon' => 'FileText'
            ],
            [
                'id' => 46,
                'module_id' => 6,
                'menu' => 'Gestión de Pagos',
                'submenu' => 'Gestión de Pagos',
                'view_path' => '/estudiantes/pagos',
                'status' => true,
                'order_num' => 3,
                'icon' => 'DollarSign'
            ],
            [
                'id' => 47,
                'module_id' => 6,
                'menu' => 'Ranking Estudiantil',
                'submenu' => 'Ranking Estudiantil',
                'view_path' => '/estudiantes/ranking',
                'status' => true,
                'order_num' => 4,
                'icon' => 'Award'
            ],
            [
                'id' => 48,
                'module_id' => 6,
                'menu' => 'Calendario Académico',
                'submenu' => 'Calendario Académico',
                'view_path' => '/estudiantes/calendario',
                'status' => true,
                'order_num' => 5,
                'icon' => 'Calendar'
            ],
            [
                'id' => 49,
                'module_id' => 6,
                'menu' => 'Notificaciones',
                'submenu' => 'Notificaciones',
                'view_path' => '/estudiantes/notificaciones',
                'status' => true,
                'order_num' => 6,
                'icon' => 'Bell'
            ],
            [
                'id' => 50,
                'module_id' => 6,
                'menu' => 'Mi Perfil',
                'submenu' => 'Mi Perfil',
                'view_path' => '/estudiantes/perfil',
                'status' => true,
                'order_num' => 7,
                'icon' => 'UserCheck'
            ],
            [
                'id' => 51,
                'module_id' => 6,
                'menu' => 'Estado de Cuenta',
                'submenu' => 'Estado de Cuenta',
                'view_path' => '/estudiantes/estado-cuenta',
                'status' => true,
                'order_num' => 8,
                'icon' => 'CreditCard'
            ],
            [
                'id' => 81,
                'module_id' => 6,
                'menu' => 'Chat docente',
                'submenu' => 'Chat Docente',
                'view_path' => '/estudiantes/chat-docente',
                'status' => true,
                'order_num' => 9,
                'icon' => 'Mail'
            ],

            // Módulo Finanzas y Pagos (module_id = 7)
            [
                'id' => 52,
                'module_id' => 7,
                'menu' => 'Dashboard Financiero',
                'submenu' => 'Dashboard Financiero',
                'view_path' => '/finanzas/dashboard',
                'status' => true,
                'order_num' => 1,
                'icon' => 'PieChart'
            ],
            [
                'id' => 53,
                'module_id' => 7,
                'menu' => 'Estado de Cuenta',
                'submenu' => 'Estado de Cuenta',
                'view_path' => '/finanzas/estado-cuenta',
                'status' => true,
                'order_num' => 2,
                'icon' => 'FileText'
            ],
            [
                'id' => 54,
                'module_id' => 7,
                'menu' => 'Gestión de Pagos',
                'submenu' => 'Gestión de Pagos',
                'view_path' => '/finanzas/gestion-pagos',
                'status' => true,
                'order_num' => 3,
                'icon' => 'CreditCard'
            ],
            [
                'id' => 55,
                'module_id' => 7,
                'menu' => 'Conciliación Bancaria',
                'submenu' => 'Conciliación Bancaria',
                'view_path' => '/finanzas/conciliacion',
                'status' => true,
                'order_num' => 4,
                'icon' => 'RefreshCw'
            ],
            [
                'id' => 56,
                'module_id' => 7,
                'menu' => 'Seguimiento de Cobros',
                'submenu' => 'Seguimiento de Cobros',
                'view_path' => '/finanzas/seguimiento-cobros',
                'status' => true,
                'order_num' => 5,
                'icon' => 'Phone'
            ],
            [
                'id' => 57,
                'module_id' => 7,
                'menu' => 'Reportes Financieros',
                'submenu' => 'Reportes Financieros',
                'view_path' => '/finanzas/reportes',
                'status' => true,
                'order_num' => 6,
                'icon' => 'BarChart'
            ],
            [
                'id' => 58,
                'module_id' => 7,
                'menu' => 'Configuración',
                'submenu' => 'Configuración',
                'view_path' => '/finanzas/configuracion',
                'status' => true,
                'order_num' => 7,
                'icon' => 'Settings'
            ],

            // Módulo Administración (module_id = 8)
            [
                'id' => 59,
                'module_id' => 8,
                'menu' => 'Dashboard Administrativo',
                'submenu' => 'Dashboard Administrativo',
                'view_path' => '/admin/dashboard',
                'status' => true,
                'order_num' => 1,
                'icon' => 'LayoutDashboard'
            ],
            [
                'id' => 60,
                'module_id' => 8,
                'menu' => 'Programación de Cursos',
                'submenu' => 'Programación de Cursos',
                'view_path' => '/admin/programacion-cursos',
                'status' => true,
                'order_num' => 2,
                'icon' => 'Calendar'
            ],
            [
                'id' => 61,
                'module_id' => 8,
                'menu' => 'Reportes de Matrícula',
                'submenu' => 'Reportes de Matrícula',
                'view_path' => '/admin/reportes-matricula',
                'status' => true,
                'order_num' => 3,
                'icon' => 'FileCheck'
            ],
            [
                'id' => 62,
                'module_id' => 8,
                'menu' => 'Reporte de Ingresos',
                'submenu' => 'Reporte de Ingresos',
                'view_path' => '/admin/reporte-graduaciones',
                'status' => true,
                'order_num' => 4,
                'icon' => 'DollarSign'
            ],
            [
                'id' => 63,
                'module_id' => 8,
                'menu' => 'Plantillas y Mailing',
                'submenu' => 'Plantillas y Mailing',
                'view_path' => '/admin/plantillas-mailing',
                'status' => true,
                'order_num' => 5,
                'icon' => 'Send'
            ],
            [
                'id' => 64,
                'module_id' => 8,
                'menu' => 'Configuración General',
                'submenu' => 'Configuración General',
                'view_path' => '/admin/configuracion',
                'status' => true,
                'order_num' => 6,
                'icon' => 'Settings'
            ],

            // Módulo Seguridad (module_id = 9)
            [
                'id' => 65,
                'module_id' => 9,
                'menu' => 'Usuarios',
                'submenu' => 'Gestión de usuarios',
                'view_path' => '/seguridad/usuarios',
                'status' => true,
                'order_num' => 1,
                'icon' => 'UserCheck'
            ],
            [
                'id' => 67,
                'module_id' => 9,
                'menu' => 'Permisos',
                'submenu' => 'Asignación de permisos',
                'view_path' => '/seguridad/permisos',
                'status' => true,
                'order_num' => 3,
                'icon' => 'Shield'
            ],
            [
                'id' => 68,
                'module_id' => 9,
                'menu' => 'Auditoría',
                'submenu' => 'Logs de auditoría',
                'view_path' => '/seguridad/auditoria',
                'status' => true,
                'order_num' => 4,
                'icon' => 'Activity'
            ],
            [
                'id' => 69,
                'module_id' => 9,
                'menu' => 'Políticas',
                'submenu' => 'Políticas de seguridad',
                'view_path' => '/seguridad/politicas',
                'status' => true,
                'order_num' => 5,
                'icon' => 'FileText'
            ],
            [
                'id' => 82,
                'module_id' => 9,
                'menu' => 'Dashboard de Seguridad',
                'submenu' => 'Dashboard de Seguridad',
                'view_path' => '/seguridad/dashboard',
                'status' => true,
                'order_num' => 6,
                'icon' => 'LayoutDashboard'
            ],
            [
                'id' => 83,
                'module_id' => 9,
                'menu' => 'Autenticación 2FA',
                'submenu' => 'Autenticación 2FA',
                'view_path' => '/seguridad/2fa',
                'status' => true,
                'order_num' => 7,
                'icon' => 'Key'
            ],
            [
                'id' => 84,
                'module_id' => 9,
                'menu' => 'Accesos',
                'submenu' => 'Accesos',
                'view_path' => '/seguridad/accesos',
                'status' => true,
                'order_num' => 8,
                'icon' => 'LogIn'
            ],
            [
                'id' => 85,
                'module_id' => 9,
                'menu' => 'Auditoria',
                'submenu' => 'Auditoria',
                'view_path' => '/seguridad/auditoria',
                'status' => true,
                'order_num' => 9,
                'icon' => 'Activity'
            ]
        ];

        foreach ($moduleViews as $moduleView) {
            ModulesViews::updateOrCreate(
                ['id' => $moduleView['id']],
                $moduleView
            );
        }
    }
}
