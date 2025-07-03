<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CoursePerfResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'course_id'       => $this->id,
            'name'            => $this->name,
            'promedio'        => $this->promedio ? round($this->promedio, 2) : null,
            'tasa_aprobacion' => $this->tasa_aprobacion ? round($this->tasa_aprobacion * 100, 2) : 0,
        ];
    }
}
