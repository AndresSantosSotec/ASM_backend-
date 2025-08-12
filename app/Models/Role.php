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
     * Permisos asociados al rol mediante la pivote rolepermissions.
     */
    public function permissions()
    {
        return $this->belongsToMany(
            \App\Models\Permisos::class, // <- usa tu modelo Permisos
            'rolepermissions',           // tabla pivote
            'role_id',                   // FK de Role en la pivote
            'permission_id'              // FK de Permisos en la pivote
        )
        ->withPivot('scope', 'assigned_at'); // campos extra de la pivote
        // ->withTimestamps(); // SOLO si la pivote tiene created_at/updated_at
    }
}
