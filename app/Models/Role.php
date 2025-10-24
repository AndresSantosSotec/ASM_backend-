<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'description',
        'is_system',
        'user_count',
        'type',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'user_count' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Permissions associated with the role via rolepermissions pivot table.
     * Uses the Permission model for role-based permissions (NOT Permisos which is for user permissions).
     */
    public function permissions()
    {
        return $this->belongsToMany(
            \App\Models\Permission::class,   // Role permissions use Permission model
            'rolepermissions',               // pivot table
            'role_id',                       // FK of Role in pivot
            'permission_id'                  // FK of Permission in pivot
        )
        ->withPivot('scope', 'assigned_at'); // extra pivot columns
        // ->withTimestamps(); // ONLY if pivot has created_at/updated_at
    }
}
