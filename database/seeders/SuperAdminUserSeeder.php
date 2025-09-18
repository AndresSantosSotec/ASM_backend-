<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permisos;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar qué columnas existen realmente en la tabla users
        $columns = Schema::getColumnListing('users');
        $this->command->info('Columnas disponibles en users: ' . implode(', ', $columns));

        // Crear el usuario SuperAdmin adaptándose a la estructura real
        $existingUser = DB::table('users')->where('email', 'superadmin@blueatlas.com')->first();
        
        // Preparar datos según las columnas que existen
        $userData = [
            'email' => 'superadmin@blueatlas.com',
            'carnet' => 'SUPERADMIN001',
            'updated_at' => now()
        ];

        // Agregar campos según disponibilidad
        if (in_array('username', $columns)) {
            $userData['username'] = 'superadmin';
        }
        if (in_array('name', $columns)) {
            $userData['name'] = 'Super Administrador';
        }
        if (in_array('password_hash', $columns)) {
            $userData['password_hash'] = Hash::make('SuperAdmin123!');
        }
        if (in_array('password', $columns)) {
            $userData['password'] = Hash::make('SuperAdmin123!');
        }
        if (in_array('first_name', $columns)) {
            $userData['first_name'] = 'Super';
        }
        if (in_array('last_name', $columns)) {
            $userData['last_name'] = 'Administrador';
        }
        if (in_array('is_active', $columns)) {
            $userData['is_active'] = true;
        }
        if (in_array('email_verified', $columns)) {
            $userData['email_verified'] = true;
        }
        if (in_array('email_verified_at', $columns)) {
            $userData['email_verified_at'] = now();
        }
        if (in_array('mfa_enabled', $columns)) {
            $userData['mfa_enabled'] = false;
        }

        if ($existingUser) {
            // Actualizar usuario existente
            DB::table('users')->where('email', 'superadmin@blueatlas.com')->update($userData);
            $superAdminId = $existingUser->id;
            $this->command->info('Usuario SuperAdmin actualizado');
        } else {
            // Crear nuevo usuario
            $userData['created_at'] = now();
            $superAdminId = DB::table('users')->insertGetId($userData);
            $this->command->info('Usuario SuperAdmin creado');
        }

        $superAdmin = (object) ['id' => $superAdminId];

        // Obtener el rol de SuperAdmin (ID 11)
        $superAdminRole = Role::find(11);
        
        if ($superAdminRole) {
            // Asignar el rol SuperAdmin al usuario en la tabla userroles
            DB::table('userroles')->updateOrInsert(
                [
                    'user_id' => $superAdmin->id,
                    'role_id' => $superAdminRole->id
                ],
                [
                    'user_id' => $superAdmin->id,
                    'role_id' => $superAdminRole->id,
                    'assigned_at' => now(),
                    'expires_at' => null
                ]
            );
        }

        // Obtener todos los permisos disponibles
        $allPermissions = Permisos::all();

        // Asignar todos los permisos al usuario SuperAdmin en la tabla userpermissions
        foreach ($allPermissions as $permission) {
            DB::table('userpermissions')->updateOrInsert(
                [
                    'user_id' => $superAdmin->id,
                    'permission_id' => $permission->id
                ],
                [
                    'user_id' => $superAdmin->id,
                    'permission_id' => $permission->id,
                    'assigned_at' => now(),
                    'scope' => 'global'
                ]
            );
        }

        $this->command->info('Usuario SuperAdmin creado exitosamente:');
        $this->command->info('Email: superadmin@blueatlas.com');
        $this->command->info('Password: SuperAdmin123!');
        $this->command->info('Carnet: SUPERADMIN001');
        $this->command->info('Rol asignado: SuperAdmin');
        $this->command->info('Permisos asignados: ' . $allPermissions->count() . ' permisos');
    }
}
