<?php
// app/Console/Commands/DetectDuplicates.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prospecto;
use App\Models\DuplicateRecord;

class DetectDuplicates extends Command
{
    protected $signature = 'app:detect-duplicates';
    protected $description = 'Detecta prospectos duplicados y los almacena en duplicate_records';

    public function handle()
    {
        $this->info('🔍 Iniciando detección de duplicados...');
        DuplicateRecord::truncate();
        $this->info('🗑 Tabla duplicate_records vaciada.');

        $saved = 0;
        Prospecto::select('id','nombre_completo','correo_electronico','telefono')
            ->orderBy('id')
            ->chunkById(200, function($prospects) use (&$saved) {
                $arr = $prospects->all();
                $n   = count($arr);
                for ($i=0; $i<$n; $i++) {
                    for ($j=$i+1; $j<$n; $j++) {
                        $p1 = $arr[$i]; $p2 = $arr[$j];
                        similar_text($p1->nombre_completo, $p2->nombre_completo, $nSim);
                        similar_text($p1->correo_electronico, $p2->correo_electronico, $eSim);
                        $tSim = ($p1->telefono === $p2->telefono) ? 100 : 0;
                        $score = round($nSim*0.5 + $eSim*0.3 + $tSim*0.2);
                        if ($score >= 80) {
                            DuplicateRecord::create([
                                'original_prospect_id'  => $p1->id,
                                'duplicate_prospect_id' => $p2->id,
                                'similarity_score'      => $score,
                                'status'                => 'pending',
                            ]);
                            $saved++;
                        }
                    }
                }
            });

        $this->info("✅ Detección finalizada. {$saved} duplicados detectados.");
    }
}
