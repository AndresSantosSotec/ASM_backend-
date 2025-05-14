<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApprovalFlow extends Model
{
    use HasFactory;

    // ▸ Si tu tabla se llama exactamente approval_flows, NO necesitas $table.
    // protected $table = 'approval_flows';

    protected $fillable = [
        'name',
        'code',
        'description',
        'scope',
        'is_active',
        'parallel',
        'auto_escalate',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'parallel'      => 'boolean',
        'auto_escalate' => 'boolean',
    ];

    /* ───── Relaciones ───── */
    public function stages()
    {
        return $this->hasMany(ApprovalStage::class)->orderBy('stage_order');
    }

    public function programs()   // tabla pivot flow_program
    {
        return $this->belongsToMany(Programa::class, 'flow_program');
    }

    /* ─── Accessors virtuales ─── */
    protected $appends = ['avg_hours', 'stages'];

    public function getAvgHoursAttribute(): int
    {
        return (int) $this->stages()->avg('max_hours') ?? 0;
    }

    public function getStagesAttribute(): int
    {
        return (int) $this->stages()->count();
    }

    /* ─── Helper para activar / desactivar ─── */
    public function toggle(): self
    {
        $this->is_active = ! $this->is_active;
        $this->save();

        return $this;
    }
}
