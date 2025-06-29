<?php
// database/seeders/PrecioProgramasSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Programa;
use App\Models\PrecioPrograma;

class PrecioProgramasSeeder extends Seeder
{
    public function run()
    {
        $datos = [
            // abreviatura => [ inscripcion, cuota_mensual ]
            'BBA'   => [1000, 1500],
            'BBA CM'=> [1000, 1230],
            'BBA BF'=> [1000, 1190],
            // asumimos que para Master 21 y 18 meses la cuota es Q.1725, para 9 meses Q.1925
            'MBA'   => [1000, 1725],
            'MLDO'  => [1000, 1725],
            'MKD'   => [1000, 1725],
            'MMK'   => [1000, 1725],
            'MHTM'  => [1000, 1725],
            'MFIN'  => [1000, 1925],
            'MPM'   => [1000, 1725],
        ];

        foreach ($datos as $abbr => [$inscripcion, $cuota]) {
            $prog = Programa::where('abreviatura', $abbr)->first();
            if (! $prog) continue;

            PrecioPrograma::updateOrCreate(
                ['programa_id' => $prog->id],
                [
                    'inscripcion'   => $inscripcion,
                    'cuota_mensual' => $cuota,
                    'meses'         => $prog->meses,
                ]
            );
        }
    }
}
