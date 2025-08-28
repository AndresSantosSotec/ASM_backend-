<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_exception_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('due_day_override')->nullable(); // Día personalizado de vencimiento
            $table->boolean('skip_late_fee')->default(false); // Exención de mora
            $table->boolean('allow_partial_payments')->default(false); // Pagos parciales
            $table->boolean('skip_blocking')->default(false); // Exención de bloqueo
            $table->json('additional_rules')->nullable(); // Reglas adicionales como JSON
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_exception_categories');
    }
};
