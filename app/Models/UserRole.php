<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'userroles';
    
    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_at',
        'expires_at',
    ];

    public $timestamps = false;

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }
}
