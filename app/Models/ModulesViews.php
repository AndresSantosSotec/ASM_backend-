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
        'icon'=> 'string'
    ];

    /**
     * Get the module that owns this view.
     */
    public function module()
    {
        return $this->belongsTo(Modules::class, 'module_id');
    }
}
