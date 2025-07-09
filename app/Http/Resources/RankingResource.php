<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RankingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'nombre'          => $this->nombre_completo,
            'programa'        => $this->nombre_del_programa,
            'semestre'        => $this->semestre_actual,
            'gpa'             => round($this->gpa_actual, 2),
            'credits'         => (int) $this->credits,
            'totalCredits'    => (int) $this->total_credits,
            'coursesCompleted'=> (int) $this->courses_completed,
            'totalCourses'    => (int) $this->total_courses,
            'ranking'         => $this->ranking_position,
            'previousRanking' => null,
            'badges'          => $this->badges ?? [],
            'progreso'        => $this->progreso ? round($this->progreso, 2) : 0,
        ];
    }
}
