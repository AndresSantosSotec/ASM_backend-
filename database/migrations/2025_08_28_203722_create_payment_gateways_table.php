<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('commission_percentage', 5, 2)->default(0); // 0.00 - 999.99%
            $table->string('api_key')->nullable();
            $table->string('merchant_id')->nullable();
            $table->boolean('active')->default(true);
            $table->json('configuration')->nullable(); // Configuraciones extra específicas por gateway
            $table->timestamps();

            $table->index(['active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
