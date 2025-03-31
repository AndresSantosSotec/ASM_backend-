<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'auditlogs';

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'target_id',
        'ip_address',
        'user_agent',
        'timestamp',
        'level',
        'details',
        'view',
    ];

    public $timestamps = false; // Si manejas manualmente el campo timestamp
}
