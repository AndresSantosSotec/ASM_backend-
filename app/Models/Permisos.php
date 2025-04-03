<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permisos extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'permissions';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'module',
        'section',
        'resource',
        'action',
        'effect',
        'description',
        'route_path',
        'file_name',
        'object_id',
        'is_enabled',
        'level'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'level' => 'integer',
        'object_id' => 'integer'
    ];

    /**
     * Get all users who have this permission.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'userpermissions', 'permission_id', 'user_id')
                   ->withPivot('assigned_at', 'scope')
                   ->withTimestamps();
    }

    /**
     * Scope a query to only include enabled permissions.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeModule($query, $module)
    {
        return $query->where('module', $module);
    }
}