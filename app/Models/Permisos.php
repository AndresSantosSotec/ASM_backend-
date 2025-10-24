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
        'action',
        'route_path',
        'name',
        'description'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'route_path' => 'string'
    ];

    /**
     * NOTA: La relación users() fue eliminada porque userpermissions ahora usa moduleview_id
     * Los permisos de usuarios se manejan directamente a través de ModulesViews
     * Esta tabla 'permissions' es solo para permisos de ROLES (con actions, effects, etc.)
     */

    /**
     * Get the module view that owns this permission.
     */
    public function moduleView()
    {
        return $this->belongsTo(ModulesViews::class, 'route_path', 'view_path');
    }


    /**
     * Scope a query to filter by module.
     */
    public function scopeModule($query, $moduleId = null)
    {
        if ($moduleId === null) {
            return $query;
        }
        return $query->whereHas('moduleView', function ($q) use ($moduleId) {
            $q->where('module_id', $moduleId);
        });
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }
}
