<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $table->string('status')->nullable()->after('interes');
        });
    }
    
    public function down(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
    
};
