<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_rule_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_rule_id')->constrained('payment_rules')->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->integer('offset_days')->default(0);
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_rule_notifications');
    }
};
