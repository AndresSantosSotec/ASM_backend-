<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStage;
use App\Models\Programa;

class ApprovalFlowSeeder extends Seeder
{
    public function run(): void
    {

        $dummyPrograms = [
            ['id'=>1,'abreviatura'=>'GEN','nombre_del_programa'=>'Genérico'],
            ['id'=>2,'abreviatura'=>'MSTR','nombre_del_programa'=>'Maestría Demo'],
            ['id'=>3,'abreviatura'=>'DIPL','nombre_del_programa'=>'Diplomado Demo'],
            ['id'=>4,'abreviatura'=>'COUR','nombre_del_programa'=>'Curso Corto Demo'],
        ];

        foreach ($dummyPrograms as $p) {
            Programa::updateOrCreate(['id'=>$p['id']], $p);
        }

        /* ====== 2. Datos base para flujos y etapas ====== */
        $flows = [
            [
                'name'        => 'Inscripción Estándar',
                'code'        => 'FLUJO-EST',
                'description' => 'Flujo de aprobación estándar para todos los programas académicos. Incluye aprobaciones comercial, financiera y académica.',
                'scope'       => 'todos',
                'is_active'   => true,
                'avg_hours'   => [24,48,72], // max_hours por etapa
            ],
            [
                'name'        => 'Inscripción Maestrías',
                'code'        => 'FLUJO-MAE',
                'description' => 'Flujo de aprobación específico para programas de maestría. Incluye aprobaciones adicionales del comité académico.',
                'scope'       => 'maestrias',
                'is_active'   => true,
                'avg_hours'   => [24,48,72,96],
            ],
            [
                'name'        => 'Inscripción Diplomados',
                'code'        => 'FLUJO-DIP',
                'description' => 'Flujo simplificado para diplomados (solo 2 etapas).',
                'scope'       => 'diplomados',
                'is_active'   => true,
                'avg_hours'   => [24,48],
            ],
            [
                'name'        => 'Inscripción Becados',
                'code'        => 'FLUJO-BEC',
                'description' => 'Flujo para becados con más revisiones.',
                'scope'       => 'custom',
                'is_active'   => false,
                'program_ids' => [2,3],   // Ejemplo: aplica a maestría y diplomado demo
                'avg_hours'   => [24,48,72,48,96],
            ],
            [
                'name'        => 'Inscripción Rápida',
                'code'        => 'FLUJO-RAP',
                'description' => 'Un solo paso con aprobación comercial rápida.',
                'scope'       => 'cursos',
                'is_active'   => true,
                'parallel'    => true,
                'avg_hours'   => [12],
            ],
        ];

        /* ====== 3. Inserción ====== */
        foreach ($flows as $flowData) {

            $stagesHours  = $flowData['avg_hours'];
            unset($flowData['avg_hours']);

            $programIds   = $flowData['program_ids'] ?? [];
            unset($flowData['program_ids']);

            $flow = ApprovalFlow::updateOrCreate(
                ['code'=>$flowData['code']],
                $flowData + [   // campos faltantes (por defecto false/null)
                    'parallel'      => $flowData['parallel']   ?? false,
                    'auto_escalate' => $flowData['auto_escalate'] ?? false
                ]
            );

            // Etapas
            $flow->stages()->delete(); // limpia, por si re-seedeas
            foreach ($stagesHours as $idx=>$hours) {
                $titles = [
                    1=>'Aprobación Comercial',
                    2=>'Aprobación Financiera',
                    3=>'Aprobación Académica',
                    4=>'Aprobación Comité',
                    5=>'Aprobación Dirección'
                ];
                $flow->stages()->create([
                    'stage_order'        => $idx+1,
                    'title'              => $titles[$idx+1] ?? 'Etapa '.$idx+1,
                    'approver_role_slug' => 'rol-'.$idx+1,
                    'max_hours'          => $hours,
                    'mandatory'          => true,
                    'notify_requester'   => true,
                ]);
            }

            // Programas custom
            if ($flow->scope === 'custom') {
                $flow->programs()->sync($programIds);
            }
        }
    }
}
