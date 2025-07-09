<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CoursePerfResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'code'         => $this->code,
            'period'       => $this->period,
            'students'     => (int) $this->students,
            'averageGrade' => $this->promedio ? round($this->promedio, 2) : null,
            'passingRate'  => $this->tasa_aprobacion ? round($this->tasa_aprobacion, 2) : null,
            'topStudent'   => $this->top_student ? [
                'id'    => $this->top_student['id'],
                'name'  => $this->top_student['name'],
                'grade' => $this->top_student['grade'],
            ] : null,
        ];
    }
}
