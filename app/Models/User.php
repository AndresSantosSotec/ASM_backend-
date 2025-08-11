<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'is_active',
        'email_verified',
        'mfa_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active'         => 'boolean',
        'email_verified'    => 'boolean',
        'mfa_enabled'       => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'last_login'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    /**
     * Obtiene el nombre completo del usuario.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Relación: asignación de rol para el usuario.
     */
    public function userRole()
    {
        return $this->hasOne(\App\Models\UserRole::class, 'user_id');
    }

    /**
     * Accesor para obtener el nombre del rol asignado.
     * Si el usuario tiene una asignación de rol y ésta tiene un rol relacionado, devuelve su nombre;
     * en caso contrario, devuelve una cadena vacía.
     *
     * @return string
     */
    public function getRolAttribute()
    {
        return $this->userRole && $this->userRole->role ? $this->userRole->role->name : '';
    }

    /**
     * Método helper para determinar si el usuario es administrador.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return strtolower($this->rol) === 'administrador';
    }

    /**
     * Relación para las sesiones activas del usuario.
     */
    public function sessions()
    {
        return $this->hasMany(\App\Models\Session::class, 'user_id');
    }

    /**
     * Relación para los registros de auditoría asociados al usuario.
     */
    public function auditLogs()
    {
        return $this->hasMany(\App\Models\AuditLog::class, 'user_id');
    }

    public function commissionRates()
    {
        return $this->hasOne(AdvisorCommissionRate::class, 'user_id');
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'userroles')->withTimestamps();
    }
}
