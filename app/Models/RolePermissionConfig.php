<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermissionConfig extends Model
{
    use HasFactory;

    protected $table = 'role_permissions_config';

    protected $fillable = [
        'role_id',
        'permission_id',
        'action',
        'scope',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'permission_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the role that owns this permission configuration.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the permission that this configuration refers to.
     */
    public function permission()
    {
        return $this->belongsTo(Permisos::class, 'permission_id');
    }
}