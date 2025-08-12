<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Nombre de la tabla (se asume que es "roles")
    protected $table = 'roles';

    // Asume que la clave primaria es "id"
    protected $primaryKey = 'id';

    // Campos asignables masivamente
    protected $fillable = [
        'name',
        'description',
        'is_system',
        'user_count',
        'type',
    ];

    // Casteo de campos
    protected $casts = [
        'is_system'   => 'boolean',
        'user_count'  => 'integer',
        'created_at'  => 'datetime',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'rolepermissions')
            ->withPivot('scope', 'assigned_at')
            ->withTimestamps();
    }
}