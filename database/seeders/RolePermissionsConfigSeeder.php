<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionsConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('role_permissions_config')->truncate();

        $now = now();
        $configs = [];

        // Administrador (ID: 1) - Acceso completo al sistema - todos los permisos
        $allPermissions = DB::table('permissions')->pluck('id')->toArray();
        foreach ($allPermissions as $permissionId) {
            $configs[] = [
                'role_id' => 1,
                'permission_id' => $permissionId,
                'action' => 'view',
                'scope' => 'self',
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        // Docente (ID: 2) - Solo módulo 5 (Portal Docente) - permisos 34-43
        for ($i = 34; $i <= 43; $i++) {
            if ($this->permissionExists($i)) {
                $configs[] = [
                    'role_id' => 2,
                    'permission_id' => $i,
                    'action' => 'view',
                    'scope' => 'self',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }

        // Estudiante (ID: 3) - Solo módulo 6 (Estudiante) - permisos 44-51, 81
        $estudiantePermissions = array_merge(range(44, 51), [81]);
        foreach ($estudiantePermissions as $permissionId) {
            if ($this->permissionExists($permissionId)) {
                $configs[] = [
                    'role_id' => 3,
                    'permission_id' => $permissionId,
                    'action' => 'view',
                    'scope' => 'self',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }

        // Administrativo (ID: 4) - Solo módulo 8 (Administrativo) - permisos 59-64
        for ($i = 59; $i <= 64; $i++) {
            if ($this->permissionExists($i)) {
                $configs[] = [
                    'role_id' => 4,
                    'permission_id' => $i,
                    'action' => 'view',
                    'scope' => 'self',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }

        // Finanzas (ID: 5) - Solo módulo 7 (Finanzas) - permisos 52-58
        for ($i = 52; $i <= 58; $i++) {
            if ($this->permissionExists($i)) {
                $configs[] = [
                    'role_id' => 5,
                    'permission_id' => $i,
                    'action' => 'view',
                    'scope' => 'self',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }

        // Seguridad (ID: 6) - Solo módulo 9 (Seguridad) - permisos 65, 67-69, 82-85
        $seguridadPermissions = array_merge([65], range(67, 69), range(82, 85));
        foreach ($seguridadPermissions as $permissionId) {
            if ($this->permissionExists($permissionId)) {
                $configs[] = [
                    'role_id' => 6,
                    'permission_id' => $permissionId,
                    'action' => 'view',
                    'scope' => 'self',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }

        // Asesor (ID: 7) - Solo módulo 2 (Prospectos) - permisos 1-5, 8-9, 12
        $asesorPermissions = array_merge(range(1, 5), range(8, 9), [12]);
        foreach ($asesorPermissions as $permissionId) {
            if ($this->permissionExists($permissionId)) {
                $configs[] = [
                    'role_id' => 7,
                    'permission_id' => $permissionId,
                    'action' => 'view',
                    'scope' => 'self',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }

        // Insert all configurations in batches for performance
        if (!empty($configs)) {
            $chunks = array_chunk($configs, 100);
            foreach ($chunks as $chunk) {
                DB::table('role_permissions_config')->insert($chunk);
            }
        }

        $this->command->info('Role permissions configuration seeded successfully.');
        $this->command->info('Total configurations inserted: ' . count($configs));
    }

    /**
     * Check if a permission exists in the database
     */
    private function permissionExists(int $permissionId): bool
    {
        return DB::table('permissions')->where('id', $permissionId)->exists();
    }
}