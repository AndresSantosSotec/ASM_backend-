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
        Schema::create('payment_rule_blocking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_rule_id')->constrained('payment_rules')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('days_after_due')->default(1);
            $table->json('affected_services'); // ['plataforma', 'evaluaciones', 'materiales']
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['payment_rule_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_rule_blocking_rules');
    }
};
