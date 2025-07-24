<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $table->string('carnet')->nullable()->after('id');
            $table->boolean('activo')->default(true)->after('carnet');
        });
    }

    public function down(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $table->dropColumn(['carnet', 'activo']);
        });
    }
};
