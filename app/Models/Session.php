<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $table = 'sessions';

    protected $fillable = [
        'user_id',
        'token_hash',
        'ip_address',
        'user_agent',
        'created_at',
        'last_activity',
        'is_active',
        'device_type',
        'platform',
        'browser',
        'start_time',
        'duration',
    ];

    public $timestamps = false; // Si administras manualmente las marcas de tiempo
}
