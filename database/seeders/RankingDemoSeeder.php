<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use App\Models\Course;
use App\Models\Inscripcion;
use App\Models\GpaHist;
use App\Models\Achievement;

class RankingDemoSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Ensure two programs exist with total_cursos set
            $bba = Programa::firstOrCreate(
                ['abreviatura' => 'BBA'],
                [
                    'nombre_del_programa' => 'Bachelor of Business Administration',
                    'meses' => 32,
                    'activo' => true,
                ]
            );
            $bba->update(['total_cursos' => 10]);

            $mba = Programa::firstOrCreate(
                ['abreviatura' => 'MBA'],
                [
                    'nombre_del_programa' => 'Master of Business Administration',
                    'meses' => 21,
                    'activo' => true,
                ]
            );
            $mba->update(['total_cursos' => 8]);

            // Make sure we have some courses
            $courses = Course::limit(3)->get();
            if ($courses->count() === 0) {
                $coursesData = [
                    ['code' => 'BBA101', 'name' => 'Introduccion a Administracion'],
                    ['code' => 'BBA102', 'name' => 'Contabilidad Basica'],
                    ['code' => 'MBA201', 'name' => 'Gerencia Estrategica'],
                ];
                foreach ($coursesData as $cd) {
                    $courses[] = Course::updateOrCreate(
                        ['code' => $cd['code']],
                        [
                            'name' => $cd['name'],
                            'area' => 'common',
                            'credits' => 3,
                            'start_date' => now()->toDateString(),
                            'end_date' => now()->addMonth()->toDateString(),
                            'schedule' => 'Lun-Vie 08:00-12:00',
                            'duration' => '4h',
                            'status' => 'approved',
                            'students' => 0,
                        ]
                    );
                }
            }

            $students = [
                ['nombre' => 'Ana Gomez',  'email' => 'ana@example.com',  'tel' => '555-0001', 'genero' => 'F', 'program' => $bba],
                ['nombre' => 'Luis Perez', 'email' => 'luis@example.com', 'tel' => '555-0002', 'genero' => 'M', 'program' => $bba],
                ['nombre' => 'Maria Lopez','email' => 'maria@example.com','tel' => '555-0003', 'genero' => 'F', 'program' => $mba],
            ];

            foreach ($students as $idx => $s) {
                $prospecto = Prospecto::create([
                    'fecha' => now()->toDateString(),
                    'nombre_completo' => $s['nombre'],
                    'telefono' => $s['tel'],
                    'correo_electronico' => $s['email'],
                    'genero' => $s['genero'],
                ]);

                EstudiantePrograma::create([
                    'prospecto_id' => $prospecto->id,
                    'programa_id' => $s['program']->id,
                    'fecha_inicio' => now()->subMonths(6),
                    'fecha_fin' => now()->addMonths(6),
                    'duracion_meses' => $s['program']->meses,
                    'inscripcion' => 1000,
                    'cuota_mensual' => 1500,
                    'inversion_total' => 20000,
                ]);

                foreach ($courses as $c) {
                    Inscripcion::create([
                        'prospecto_id' => $prospecto->id,
                        'course_id' => $c->id,
                        'semestre' => '2025A',
                        'credits' => $c->credits,
                        'calificacion' => 70 + $idx * 5,
                    ]);
                }

                GpaHist::create([
                    'prospecto_id' => $prospecto->id,
                    'semestre' => '2025A',
                    'gpa' => 8.5 - $idx * 0.3,
                ]);

                Achievement::create([
                    'prospecto_id' => $prospecto->id,
                    'tipo' => 'excelencia',
                    'semestre' => '2025A',
                ]);
            }
        });
    }
}
