<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'     => $this->id,
            'nombre' => $this->nombre_completo,
            'programas' => $this->programas->map(fn($p) => [
                'programa'      => optional($p->programa)->nombre_del_programa,
                'fecha_inicio'  => $p->fecha_inicio,
                'fecha_fin'     => $p->fecha_fin,
            ]),
            'inscripciones' => $this->inscripciones->map(fn($i) => [
                'course'      => optional($i->course)->name,
                'semestre'    => $i->semestre,
                'credits'     => $i->credits,
                'calificacion'=> $i->calificacion,
            ]),
            'gpa_hist' => $this->gpaHist->map(fn($g) => [
                'semestre' => $g->semestre,
                'gpa'      => $g->gpa,
            ]),
            'achievements' => $this->achievements->map(fn($a) => [
                'tipo'     => $a->tipo,
                'semestre' => $a->semestre,
            ]),
        ];
    }
}
