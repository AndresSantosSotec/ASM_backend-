<?php
// database/seeders/CoursesSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Programa;

class CoursesSeeder extends Seeder
{
    public function run()
    {
        // Definimos todos los cursos, agrupados por abreviatura de programa
        $blocks = [

            // Bachelor of Business Administration (BBA)
            'BBA'    => [
                // Área común
                ['BBA01','Comunicación y Redacción Ejecutiva',3,'common'],
                ['BBA02','Razonamiento Crítico',5,'common'],
                ['BBA03','Derecho Empresarial',3,'common'],
                ['BBA04','Estadística aplicada',4,'common'],
                ['BBA05','Introducción a la microeconomía',3,'common'],
                ['BBA06','Fundamentos de la Negociación',3,'common'],
                ['BBA07','Manejo de Crisis Económica',4,'common'],
                ['BBA08','Principios de Estrategia (Manejo de Herramientas Estratégicas)',4,'common'],
                ['BBA09','Principios de Estrategia (Manejo de Herramientas Estratégicas)',4,'common'],
                ['BBA10','Introducción a la Macroeconomía',3,'common'],
                ['BBA11','Excel Ejecutivo',3,'common'],
                ['BBA12','Emprendimiento e innovación',3,'common'],
                ['BBA13','Introducción al Marketing Digital',3,'common'],
                ['BBA14','Contabilidad Aplicada',4,'common'],
                ['BBA15','Contabilidad Financiera',4,'common'],
                ['BBA16','Análisis de Mercados y Marketing de Servicios',4,'common'],
                ['BBA17','Marketing Plan',4,'common'],
                ['BBA18','Introducción de Big Data',5,'common'],
                ['BBA19','Finanzas para Ejecutivos',5,'common'],
                ['BBA20','Responsabilidad Social Corporativa',3,'common'],
                ['BBA21','Planificación y Organización de la Producción',4,'common'],
                ['BBA22','Gestión Comercial',3,'common'],
                ['BBA23','Gestión Estratégica de Talento Humano',3,'common'],
                ['BBA24','Inteligencia Artificial',4,'common'],
                ['BBA25','Matemática Financiera',4,'common'],
                ['BBA26','Finanzas para toma de decisiones',5,'common'],
                ['BBA27','Innovación en Ventas',4,'common'],
                ['BBA28','Administración tributaria',4,'common'],
                ['BBA29','Power BI',5,'common'],

                // Área especialidad: Commercial Management
                ['BBACM24','Psicología y Análisis del Consumidor',4,'specialty'],
                ['BBACM25','Prospección Estratégica en Ventas',5,'specialty'],
                ['BBACM26','Presentación Efectiva de Ventas',4,'specialty'],
                ['BBACM27','Negociación',5,'specialty'],
                ['BBACM28','Manejo de Objeciones en Ventas',4,'specialty'],
                ['BBACM29','Planeación Estratégica de Ventas',5,'specialty'],

                // Área especialidad: Banking & Fintech
                ['BBABF25','Lavado de Activos',5,'specialty'],
                ['BBABF26','Banca Digital',5,'specialty'],
                ['BBABF27','Innovación en Finanzas: Fintech y Blockchain (Neobanca)',5,'specialty'],
                ['BBABF28','Camino Disruptivo en Banca',4,'specialty'],
                ['BBABF29','Banca Internacional',5,'specialty'],

                // Área cierre del programa
                ['BBA30','Seminario de Gerencia',5,'specialty'],
                ['BBA31','Proyecto de Grado I',6,'specialty'],
                ['BBA32','Proyecto de Grado II',6,'specialty'],
                ['BBA33','Certificación Internacional',8,'specialty'],
            ],

            // Master of Business Administration (MBA)
            'MBA'    => [
                // Área especialidad
                ['MBA01','Gestión de Crisis y Resiliencia',4,'specialty'],
                ['MBA02','Negociación y Resolución de Conflictos',4,'specialty'],
                ['MBA03','Macroeconomía y Políticas Económicas',4,'specialty'],
                ['MBA04','E-Business y Estrategias Digitales',4,'specialty'],
                ['MBA05','Gerencia de Operaciones y Logística',4,'specialty'],
                ['MBA06','Benchmarking y Competitividad',4,'specialty'],
                ['MBA07','Comunicación Efectiva, Branding y Marca Personal',4,'specialty'],
                ['MBA08','Big Data y Análisis de Datos',5,'specialty'],
                ['MBA09','Marketing Estratégico',4,'specialty'],
                ['MBA10','Estrategia Corporativa',4,'specialty'],
                ['MBA11','Experiencia del Cliente y CRM',4,'specialty'],
                ['MBA12','Análisis de Estados Financieros',4,'specialty'],
                ['MBA13','Cash Flow Management',4,'specialty'],
                ['MBA14','Finanzas Corporativas',5,'specialty'],

                // Área cierre del programa
                ['MBA15','Seminario de Gerencia',5,'specialty'],
                ['MBA16','Escritura de Caso',6,'specialty'],
                ['MBA17','Proyecto de Grado I',6,'specialty'],
                ['MBA18','Proyecto de Grado II',6,'specialty'],
                ['MBA19','Certificación Internacional',8,'specialty'],
            ],

            // Master of Logistics in Operations Management (MLDO)
            'MLDO'   => [
                // Área común
                ['MLDO01','Gestión de Crisis y Resiliencia',4,'common'],
                ['MLDO02','Negociación y Resolución de Conflictos',4,'common'],
                ['MLDO03','Macroeconomía y Políticas Económicas',4,'common'],
                ['MLDO04','Gerencia de Operaciones y Logística',4,'common'],
                ['MLDO05','Benchmarking y Competitividad',4,'common'],
                ['MLDO06','Comunicación Efectiva, Branding y Marca Personal',4,'common'],
                ['MLDO07','Big Data y Análisis de Datos',4,'common'],

                // Área especialidad
                ['MLDO08','Dirección y Gestión Avanzada de la Cadena de Suministro',5,'specialty'],
                ['MLDO09','Logística y Transporte Nacional e Internacional',4,'specialty'],
                ['MLDO10','Análisis Predictivo y Gestión de la Demanda',4,'specialty'],
                ['MLDO11','Gestión de Riesgos y Cumplimiento Normativo en Logística',4,'specialty'],
                ['MLDO12','Finanzas y Optimización de Costos en Logística',5,'specialty'],
                ['MLDO13','Lean Management y Mejora Continua',4,'specialty'],
                ['MLDO14','Transformación Digital y Automatización en Logística',5,'specialty'],

                // Área cierre del programa
                ['MLDO15','Seminario de Gerencia',5,'specialty'],
                ['MLDO16','Escritura de Caso',6,'specialty'],
                ['MLDO17','Capstone Project I',6,'specialty'],
                ['MLDO18','Capstone Project II and Business Plan',6,'specialty'],
                ['MLDO19','Certificación Internacional',8,'specialty'],
            ],

            // Master of Digital Marketing (MKD)
            'MKD'    => [
                ['MKD01','Gestión de Crisis y Resiliencia',4,'common'],
                ['MKD02','Negociación y Resolución de Conflictos',4,'common'],
                ['MKD03','Macroeconomía y Políticas Económicas',4,'common'],
                ['MKD04','Gerencia de Operaciones y Logística',4,'common'],
                ['MKD05','Benchmarking y Competitividad',4,'common'],
                ['MKD06','Comunicación Efectiva, Branding y Marca Personal',4,'common'],
                ['MKD07','Big Data y Análisis de Datos',4,'common'],

                ['MKD08','Estrategias de Lead Generation y Omnicanalidad',4,'specialty'],
                ['MKD09','Innovación y Transformación Digital en Marketing',4,'specialty'],
                ['MKD10','Estrategias de Marketing de Afiliados y Asociados',4,'specialty'],
                ['MKD11','SEO, SEM y Optimización de Motores de Búsqueda',4,'specialty'],
                ['MKD12','Gestión Estratégica de Redes Sociales',4,'specialty'],
                ['MKD13','Gestión Financiera de Proyectos Digitales',4,'specialty'],
                ['MKD14','Neuromarketing y Psicología del Consumidor',4,'specialty'],

                ['MKD15','Seminario de Gerencia',5,'specialty'],
                ['MKD16','Escritura de Caso',6,'specialty'],
                ['MKD17','Capstone Project I',6,'specialty'],
                ['MKD18','Capstone Project II and Business Plan',6,'specialty'],
                ['MKD19','Certificación Internacional',8,'specialty'],
            ],

            // Master of Marketing in Commercial Management (MMK)
            'MMK'    => [
                ['MMK01','Gestión de Crisis y Resiliencia',4,'common'],
                ['MMK02','Negociación y Resolución de Conflictos',4,'common'],
                ['MMK03','Macroeconomía y Políticas Económicas',4,'common'],
                ['MMK04','Gerencia de Operaciones y Logística',4,'common'],
                ['MMK05','Benchmarking y Competitividad',4,'common'],
                ['MMK06','Comunicación Efectiva, Branding y Marca Personal',4,'common'],
                ['MMK07','Big Data y Análisis de Datos',4,'common'],

                ['MMK08','Marketing Estratégico Financiero',4,'specialty'],
                ['MMK09','Gestión y Dirección de Equipos Comerciales',3,'specialty'],
                ['MMK10','Key Account Management',5,'specialty'],
                ['MMK11','Analítica para Mercadeo y Ventas',5,'specialty'],
                ['MMK12','Estrategias Digitales en la Gestión Comercial',3,'specialty'],
                ['MMK13','Negociación Avanzada y Gestión de Conflictos',4,'specialty'],
                ['MMK14','Comportamiento del Consumidor y Neuromarketing',4,'specialty'],

                ['MMK15','Seminario de Gerencia',5,'specialty'],
                ['MMK16','Escritura de Caso',6,'specialty'],
                ['MMK17','Capstone Project I',6,'specialty'],
                ['MMK18','Capstone Project II and Business Plan',6,'specialty'],
                ['MMK19','Certificación Internacional',8,'specialty'],
            ],

            // Master in Human Talent Management (MHTM)
            'MHTM'   => [
                ['MHTM01','Gestión de Crisis y Resiliencia',4,'common'],
                ['MHTM02','Negociación y Resolución de Conflictos',4,'common'],
                ['MHTM03','Macroeconomía y Políticas Económicas',4,'common'],
                ['MHTM04','Gerencia de Operaciones y Logística',4,'common'],
                ['MHTM05','Benchmarking y Competitividad',4,'common'],
                ['MHTM06','Comunicación Efectiva, Branding y Marca Personal',4,'common'],
                ['MHTM07','Big Data y Análisis de Datos',4,'common'],

                ['MHTM08','Liderazgo de Equipos de Alto Rendimiento y Cultura Organizacional',4,'specialty'],
                ['MHTM09','Legislación Laboral y Compliance Global',4,'specialty'],
                ['MHTM10','Gestión del Talento y Desarrollo Organizacional',4,'specialty'],
                ['MHTM11','Reclutamiento Estratégico y Retención de Talento',4,'specialty'],
                ['MHTM12','Métricas y Análisis de Rendimiento en Talento Humano',4,'specialty'],
                ['MHTM13','Finanzas para la Gestión del Talento Humano',4,'specialty'],
                ['MHTM14','Transformación Digital y Ética en la Gestión del Talento Humano',4,'specialty'],

                ['MHTM15','Seminario de Gerencia',5,'specialty'],
                ['MHTM16','Escritura de Caso',6,'specialty'],
                ['MHTM17','Capstone Project I',6,'specialty'],
                ['MHTM18','Capstone Project II and Business Plan',6,'specialty'],
                ['MHTM19','Certificación Internacional',8,'specialty'],
            ],

            // Master of Financial Management (MFIN)
            'MFIN'   => [
                ['MFIN01','Gestión de Crisis y Resiliencia',4,'common'],
                ['MFIN02','Negociación y Resolución de Conflictos',4,'common'],
                ['MFIN03','Macroeconomía y Políticas Económicas',4,'common'],
                ['MFIN04','Gerencia de Operaciones y Logística',4,'common'],
                ['MFIN05','Benchmarking y Competitividad',4,'common'],
                ['MFIN06','Comunicación Efectiva, Branding y Marca Personal',4,'common'],
                ['MFIN07','Big Data y Análisis de Datos',4,'common'],

                ['MFIN08','Planeación Financiera y Presupuestaria',4,'specialty'],
                ['MFIN09','Valoración de Empresas y Estrategias de M&A',5,'specialty'],
                ['MFIN10','Finanzas Corporativas Internacionales',5,'specialty'],
                ['MFIN11','Gestión de Riesgos Financieros y Seguros',5,'specialty'],
                ['MFIN12','Finanzas Sostenibles y ESG',5,'specialty'],
                ['MFIN13','Inversiones y Gestión de Activos',5,'specialty'],
                ['MFIN14','Innovación Financiera y Tecnologías Fintech',5,'specialty'],

                ['MFIN15','Seminario de Gerencia',5,'specialty'],
                ['MFIN16','Escritura de Caso',6,'specialty'],
                ['MFIN17','Capstone Project I',6,'specialty'],
                ['MFIN18','Capstone Project II and Business Plan',6,'specialty'],
                ['MFIN19','Certificación Internacional',8,'specialty'],
            ],

            // Master of Project Management (MPM)
            'MPM'    => [
                ['MPM01','Gestión de Crisis y Resiliencia',4,'common'],
                ['MPM02','Negociación y Resolución de Conflictos',4,'common'],
                ['MPM03','Macroeconomía y Políticas Económicas',4,'common'],
                ['MPM04','Gerencia de Operaciones y Logística',4,'common'],
                ['MPM05','Benchmarking y Competitividad',4,'common'],
                ['MPM06','Comunicación Efectiva, Branding y Marca Personal',4,'common'],
                ['MPM07','Big Data y Análisis de Datos',4,'common'],

                ['MPM08','Formulación y Evaluación de Proyectos',5,'specialty'],
                ['MPM09','Metodología SCRUM y enfoque ágil',5,'specialty'],
                ['MPM10','Gestión del tiempo, presupuestos y costos en los proyectos',5,'specialty'],
                ['MPM11','Gestión de la calidad, riesgos y recursos en los proyectos',5,'specialty'],
                ['MPM12','Herramientas y Metodologías Ágiles para Sistemas de Gestión de Proyectos',5,'specialty'],
                ['MPM13','Habilidades de un PMP',5,'specialty'],
                ['MPM14','Design Thinking Pro',5,'specialty'],

                ['MPM15','Seminario de Gerencia',5,'specialty'],
                ['MPM16','Escritura de Caso',6,'specialty'],
                ['MPM17','Capstone Project I',6,'specialty'],
                ['MPM18','Capstone Project II and Business Plan',6,'specialty'],
                ['MPM19','Certificación Internacional',8,'specialty'],
            ],

        ];

        foreach ($blocks as $abbr => $list) {
            // buscamos el programa
            $prog = Programa::where('abreviatura',$abbr)->first();
            if (! $prog) {
                $this->command->warn("Programa {$abbr} no existe, salto todos sus cursos.");
                continue;
            }

            foreach ($list as $row) {
                [$code,$name,$credits,$area] = $row;

                $course = Course::updateOrCreate(
                    ['code'=>$code],
                    [
                        'name'           => $name,
                        'credits'        => $credits,
                        'area'           => $area,
                        'start_date'     => now()->toDateString(),
                        'end_date'       => now()->addMonth()->toDateString(),
                        'schedule'       => 'Lun-Vie 08:00-12:00',
                        'duration'       => '4h',
                        'facilitator_id' => null,
                        'status'         => 'draft',
                        'students'       => 0,
                    ]
                );

                // relacionar al programa sin duplicar
                $course->programas()->syncWithoutDetaching([$prog->id]);
            }
        }

        $this->command->info("✅ Todos los cursos han sido seedados y vinculados a sus programas.");
    }
}
