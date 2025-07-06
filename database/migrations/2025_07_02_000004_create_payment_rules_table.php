<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('due_day');
            $table->decimal('late_fee_amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('block_after_months')->default(0);
            $table->boolean('send_automatic_reminders')->default(false);
            $table->json('gateway_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_rules');
    }
};
