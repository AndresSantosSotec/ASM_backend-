<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApprovalStage extends Model
{
    use HasFactory;

    // protected $table = 'approval_stages';

    protected $fillable = [
        'flow_id',
        'stage_order',
        'title',
        'approver_role_id',
        'approver_role_slug',
        'max_hours',
        'mandatory',
        'notify_requester',
    ];

    protected $casts = [
        'mandatory'        => 'boolean',
        'notify_requester' => 'boolean',
        'max_hours'        => 'integer',
    ];

    /* ───── Relaciones ───── */
    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'flow_id');
    }
}
