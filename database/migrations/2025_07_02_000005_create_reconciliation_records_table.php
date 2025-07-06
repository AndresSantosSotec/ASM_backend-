<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_id')->nullable()->constrained('prospectos')->nullOnDelete();
            $table->string('bank');
            $table->string('reference');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('auth_number')->nullable();
            $table->string('status');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
    }
};
