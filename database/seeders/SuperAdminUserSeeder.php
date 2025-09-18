<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permisos;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SuperAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear el usuario SuperAdmin usando consulta directa para evitar SoftDeletes
        $existingUser = DB::table('users')->where('email', 'superadmin@blueatlas.com')->first();
        
        if ($existingUser) {
            // Actualizar usuario existente
            DB::table('users')->where('email', 'superadmin@blueatlas.com')->update([
                'username' => 'superadmin',
                'password_hash' => Hash::make('SuperAdmin123!'),
                'first_name' => 'Super',
                'last_name' => 'Administrador',
                'carnet' => 'SUPERADMIN001',
                'is_active' => true,
                'email_verified' => true,
                'mfa_enabled' => false,
                'updated_at' => now()
            ]);
            $superAdminId = $existingUser->id;
        } else {
            // Crear nuevo usuario
            $superAdminId = DB::table('users')->insertGetId([
                'username' => 'superadmin',
                'email' => 'superadmin@blueatlas.com',
                'password_hash' => Hash::make('SuperAdmin123!'),
                'first_name' => 'Super',
                'last_name' => 'Administrador',
                'carnet' => 'SUPERADMIN001',
                'is_active' => true,
                'email_verified' => true,
                'mfa_enabled' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
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
