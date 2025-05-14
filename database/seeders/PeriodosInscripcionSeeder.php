<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PeriodoInscripcion;
use App\Models\Programa;
use Carbon\Carbon;

class PeriodosInscripcionSeeder extends Seeder
{
    public function run()
    {
        // Recoge todos los IDs de programas para el pivot (representa "Todos")
        $allProgramas = Programa::pluck('id')->toArray();

        $periodos = [
            [
                'nombre'           => 'Primavera 2025',
                'codigo'           => 'PRIM-2025',
                'fecha_inicio'     => '2025-02-01',
                'fecha_fin'        => '2025-04-30',
                'descripcion'      => 'Periodo de primavera 2025',
                'cupos_total'      => 0,
                'descuento'        => 0,
                'activo'           => false,
                'visible'          => true,
                'notificaciones'   => true,
            ],
            [
                'nombre'           => 'Verano 2025',
                'codigo'           => 'VER-2025',
                'fecha_inicio'     => '2025-05-01',
                'fecha_fin'        => '2025-07-31',
                'descripcion'      => 'Periodo de verano 2025',
                'cupos_total'      => 0,
                'descuento'        => 0,
                'activo'           => false,
                'visible'          => true,
                'notificaciones'   => true,
            ],
            [
                'nombre'           => 'Otoño 2025',
                'codigo'           => 'OTO-2025',
                'fecha_inicio'     => '2025-08-01',
                'fecha_fin'        => '2025-10-31',
                'descripcion'      => 'Periodo de otoño 2025',
                'cupos_total'      => 0,
                'descuento'        => 0,
                'activo'           => false,
                'visible'          => true,
                'notificaciones'   => true,
            ],
            [
                'nombre'           => 'Invierno 2025',
                'codigo'           => 'INV-2025',
                'fecha_inicio'     => '2025-11-01',
                'fecha_fin'        => '2025-12-31',
                'descripcion'      => 'Periodo de invierno 2025',
                'cupos_total'      => 0,
                'descuento'        => 0,
                'activo'           => false,
                'visible'          => true,
                'notificaciones'   => true,
            ],
        ];

        foreach ($periodos as $attrs) {
            $periodo = PeriodoInscripcion::create(array_merge($attrs, [
                'inscritos_count'     => 0,
                'fecha_creacion'      => Carbon::now(),
                'fecha_actualizacion' => Carbon::now(),
            ]));

            // Vincula con todos los programas para que aparezca “Todos”
            $periodo->programas()->sync($allProgramas);
        }
    }
}
