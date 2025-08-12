<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Crea ambas columnas como NULLables (no rompe datos existentes)
            if (!Schema::hasColumn('permissions', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('permissions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('permissions', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
