<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Moduleview extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'moduleviews';

    protected $fillable = [
        'module_id',
        'menu',
        'submenu',
        'view_path',
        'status',
        'order_num',
        'icon',
    ];
}
