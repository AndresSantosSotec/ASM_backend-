<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RankingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'       => $this->id,
            'nombre'   => $this->nombre_completo,
            'gpa'      => round($this->gpa_actual, 2),
            'progreso' => $this->progreso ? round($this->progreso, 2) : 0,
        ];
    }
}
