<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulesViews extends Model
{
    use HasFactory;

    public $timestamps = false; // Desactiva los timestamps

    protected $table = 'moduleviews';
    protected $primaryKey = 'id';

    protected $fillable = [
        'module_id',
        'menu',
        'submenu',
        'view_path',
        'status',
        'order_num',
        'icon'
    ];

    protected $casts = [
        'status' => 'boolean',
        'order_num' => 'integer',
        'icon' => 'string'
    ];

    /**
     * Get the module that owns this view.
     */
    public function module()
    {
        return $this->belongsTo(Modules::class, 'module_id', 'id');
    }

    /**
     * Get all role permissions associated with this module view.
     * Role permissions define what actions (view, create, edit, delete, export) can be performed.
     * Relationship: permissions.moduleview_id (FK) → moduleviews.id (PK)
     */
    public function rolePermissions()
    {
        return $this->hasMany(Permission::class, 'moduleview_id', 'id');
    }

    /**
     * Get all user permissions associated with this module view.
     * User permissions define which users can access this view.
     * Relationship: permisos.moduleview_id (FK) → moduleviews.id (PK)
     */
    public function permissions()
    {
        return $this->hasMany(Permisos::class, 'moduleview_id', 'id');
    }
}
