<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'moduleview_id',
        'name',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'rolepermissions')
            ->withPivot('scope', 'assigned_at')
            ->withTimestamps();
    }

    public function moduleview(): BelongsTo
    {
        return $this->belongsTo(Moduleview::class, 'moduleview_id');
    }
}
