<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPermisos extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'userpermissions';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Disable automatic timestamps management.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'moduleview_id',
        'assigned_at',
        'scope'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'user_id' => 'integer',
        'moduleview_id' => 'integer'
    ];

    /**
     * Get the user associated with the permission.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the moduleview details.
     */
    public function moduleView()
    {
        return $this->belongsTo(ModulesViews::class, 'moduleview_id', 'id');
    }
}
