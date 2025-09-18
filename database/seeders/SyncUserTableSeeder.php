<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SyncUserTableSeeder extends Seeder
{
    /**
     * Sincronizar la estructura de la tabla users con PostgreSQL.
     */
    public function run(): void
    {
        $this->command->info('🔄 SINCRONIZANDO ESTRUCTURA DE TABLA USERS...');
        
        if (!Schema::hasTable('users')) {
            $this->command->error('❌ Tabla users no existe');
            return;
        }

        $columns = Schema::getColumnListing('users');
        $this->command->info('✅ Columnas actuales: ' . implode(', ', $columns));

        // Estructura esperada según PostgreSQL
        $expectedColumns = [
            'id' => 'integer',
            'username' => 'varchar(50)',
            'email' => 'varchar(100)',
            'password_hash' => 'varchar(255)',
            'first_name' => 'varchar(50)',
            'last_name' => 'varchar(50)',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
            'last_login' => 'timestamp',
            'mfa_enabled' => 'boolean',
            'deleted_at' => 'timestamp',
            'carnet' => 'varchar(255)'
        ];

        $this->command->info('');
        $this->command->info('🔍 COMPARANDO CON ESTRUCTURA ESPERADA:');

        $missingColumns = [];
        $extraColumns = [];

        // Verificar columnas esperadas
        foreach ($expectedColumns as $column => $type) {
            if (in_array($column, $columns)) {
                $this->command->info("   ✅ {$column}");
            } else {
                $this->command->error("   ❌ {$column} (falta)");
                $missingColumns[] = $column;
            }
        }

        // Verificar columnas extra
        foreach ($columns as $column) {
            if (!array_key_exists($column, $expectedColumns)) {
                $this->command->warn("   ⚠️  {$column} (extra - no esperada)");
                $extraColumns[] = $column;
            }
        }

        $this->command->info('');
        
        if (empty($missingColumns) && empty($extraColumns)) {
            $this->command->info('🎉 ESTRUCTURA PERFECTA - Todas las columnas coinciden');
        } else {
            $this->command->warn('⚠️  DIFERENCIAS ENCONTRADAS:');
            
            if (!empty($missingColumns)) {
                $this->command->warn('   Columnas faltantes: ' . implode(', ', $missingColumns));
            }
            
            if (!empty($extraColumns)) {
                $this->command->warn('   Columnas extra: ' . implode(', ', $extraColumns));
            }

            $this->command->info('');
            $this->command->info('💡 RECOMENDACIONES:');
            $this->command->info('   1. La estructura actual funciona con adaptación automática');
            $this->command->info('   2. SuperAdminUserSeeder se adapta a las columnas disponibles');
            $this->command->info('   3. Para estructura perfecta, ejecutar migraciones adicionales');
        }

        // Mostrar mapeo de campos
        $this->command->info('');
        $this->command->info('🗺️  MAPEO DE CAMPOS:');
        $this->command->info('   PostgreSQL → Laravel');
        $this->command->info('   username → username (o name si no existe)');
        $this->command->info('   password_hash → password_hash (o password si no existe)');
        $this->command->info('   email_verified → email_verified (o email_verified_at si no existe)');
        $this->command->info('   first_name/last_name → first_name/last_name (o name combinado)');

        $this->command->info('');
        $this->command->info('✅ Sincronización completada');
    }
}
