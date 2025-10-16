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
        'moduleview_id',
        'name',
        'description'
    ];

    /**
     * Boot method to auto-generate name if not provided
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($permission) {
            if (empty($permission->name) && $permission->moduleview_id && $permission->action) {
                $moduleView = ModulesViews::find($permission->moduleview_id);
                if ($moduleView) {
                    $permission->name = $permission->action . ':' . $moduleView->view_path;
                }
            }
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'moduleview_id' => 'integer'
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
     * Get the module view that owns this permission.
     */
    public function moduleView()
    {
        return $this->belongsTo(ModulesViews::class, 'moduleview_id', 'id');
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
