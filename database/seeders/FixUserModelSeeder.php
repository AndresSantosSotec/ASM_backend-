<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixUserModelSeeder extends Seeder
{
    /**
     * Verificar y corregir problemas con el modelo User.
     */
    public function run(): void
    {
        $this->command->info('🔧 VERIFICANDO ESTRUCTURA DE TABLA USERS...');
        
        if (!Schema::hasTable('users')) {
            $this->command->error('❌ Tabla users no existe');
            return;
        }

        $columns = Schema::getColumnListing('users');
        $this->command->info('✅ Columnas en users: ' . implode(', ', $columns));

        // Verificar si deleted_at existe
        $hasDeletedAt = in_array('deleted_at', $columns);
        $this->command->info($hasDeletedAt ? '✅ Columna deleted_at: EXISTE' : '⚠️  Columna deleted_at: NO EXISTE');

        // Verificar campos esperados por el seeder
        $expectedFields = ['username', 'email', 'password_hash', 'first_name', 'last_name', 'carnet', 'is_active', 'email_verified', 'mfa_enabled'];
        
        $this->command->info('');
        $this->command->info('🔍 VERIFICANDO CAMPOS ESPERADOS:');
        
        foreach ($expectedFields as $field) {
            $exists = in_array($field, $columns);
            $status = $exists ? '✅' : '❌';
            $this->command->info("   {$status} {$field}");
        }

        // Si no existe deleted_at, sugerir crear migración o remover SoftDeletes
        if (!$hasDeletedAt) {
            $this->command->warn('');
            $this->command->warn('⚠️  RECOMENDACIÓN:');
            $this->command->warn('   El modelo User usa SoftDeletes pero la columna deleted_at no existe.');
            $this->command->warn('   Opciones:');
            $this->command->warn('   1. Remover "use SoftDeletes" del modelo User');
            $this->command->warn('   2. Crear migración para agregar deleted_at');
        }

        $this->command->info('');
        $this->command->info('✅ Verificación completada');
    }
}
