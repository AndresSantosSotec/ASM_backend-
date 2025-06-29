<?php
// database/seeders/ProgramasSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Programa;

class ProgramasSeeder extends Seeder
{
    public function run()
    {
        $lista = [
            ['abreviatura'=>'BBA',   'nombre_del_programa'=>'Bachelor of Business Administration',                 'meses'=>32],
            ['abreviatura'=>'BBA CM','nombre_del_programa'=>'Bachelor of Business Administration in Commercial Management','meses'=>24],
            ['abreviatura'=>'BBA BF','nombre_del_programa'=>'Bachelor of Business Administration in Banking and Fintech','meses'=>24],
            ['abreviatura'=>'MBA',   'nombre_del_programa'=>'Master of Business Administration',                       'meses'=>21],
            ['abreviatura'=>'MLDO',  'nombre_del_programa'=>'Master of Logistics in Operations Management',     'meses'=>18],
            ['abreviatura'=>'MKD',   'nombre_del_programa'=>'Master of Digital Marketing',                           'meses'=>18],
            ['abreviatura'=>'MMK',   'nombre_del_programa'=>'Master of Marketing in Commercial Management',          'meses'=>18],
            ['abreviatura'=>'MHTM',  'nombre_del_programa'=>'Master in Human Talent Management',                  'meses'=>18],
            ['abreviatura'=>'MFIN',  'nombre_del_programa'=>'Master of Financial Management',                    'meses'=>9],
            ['abreviatura'=>'MPM',   'nombre_del_programa'=>'Master of Project Management',                      'meses'=> (int)config('app.mpm_meses', 18)],
        ];

        foreach ($lista as $p) {
            Programa::updateOrCreate(
                ['abreviatura' => $p['abreviatura']],
                [
                  'nombre_del_programa'    => $p['nombre_del_programa'],
                  'meses'                  => $p['meses'],
                  'activo'                 => true,
                ]
            );
        }
    }
}
