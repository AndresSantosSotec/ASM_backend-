<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Convenio;
use App\Models\Programa;
use App\Models\PrecioConvenioPrograma;
use Illuminate\Support\Carbon;

class ConvenioSeeder extends Seeder
{
    public function run(): void
    {
        $convenio = Convenio::firstOrCreate(
            ['nombre' => 'Alianza Corporativa'],
            ['descripcion' => 'Precios corporativos para empresas aliadas.', 'activo' => true]
        );

        $precios = [
            ['abbr' => 'BBA', 'meses' => 8,  'cuota' => 1500.00],
            ['abbr' => 'BBA', 'meses' => 12, 'cuota' => 975.00],
            ['abbr' => 'BBA', 'meses' => 24, 'cuota' => 925.00],
            ['abbr' => 'BBA', 'meses' => 32, 'cuota' => 885.00],
            ['abbr' => 'MAE', 'meses' => 21, 'cuota' => 1285.00],
            ['abbr' => 'MAE', 'meses' => 18, 'cuota' => 1285.00],
            ['abbr' => 'MAE', 'meses' => 9,  'cuota' => 1460.00],
        ];

        foreach ($precios as $dato) {
            $programa = Programa::firstOrCreate(
                ['abreviatura' => $dato['abbr'], 'meses' => $dato['meses']],
                [
                    'nombre_del_programa' => $dato['abbr'],
                    'fecha_creacion'      => Carbon::now(),
                    'activo'              => true,
                ]
            );

            PrecioConvenioPrograma::updateOrCreate(
                [
                    'convenio_id' => $convenio->id,
                    'programa_id' => $programa->id,
                ],
                [
                    'cuota_mensual' => $dato['cuota'],
                    'meses'         => $dato['meses'],
                    'inscripcion'   => null,
                ]
            );
        }
    }
}
