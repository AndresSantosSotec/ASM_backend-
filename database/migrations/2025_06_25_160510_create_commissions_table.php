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
        Schema::create('commissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_income', 14)->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('rate_applied', 5)->default(0);
            $table->decimal('commission_amount', 14)->default(0);
            $table->decimal('difference', 14)->default(0);
            $table->bigInteger('config_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
