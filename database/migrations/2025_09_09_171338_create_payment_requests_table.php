<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 36)->unique(); // UUID
            $table->bigInteger('user_id');
            $table->json('request_payload');
            $table->json('response_payload');
            $table->integer('response_status');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Index for cleanup queries
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_requests');
    }
};