<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modules extends Model
{
    use HasFactory;

    // Desactivar los timestamps
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'modules';

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
        'name',
        'description',
        'status',
        'view_count',
        'icon',
        'order_num'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
        'view_count' => 'integer',
        'order_num' => 'integer',
        'icon' => 'string'
    ];

    /**
     * Get all views associated with this module.
     */
    public function views()
    {
        return $this->hasMany(ModulesViews::class, 'module_id');
    }
}
