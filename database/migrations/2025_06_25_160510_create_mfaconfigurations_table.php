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
        Schema::create('mfaconfigurations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->enum('method', ['App', 'SMS', 'Email', 'FIDO2'])->nullable();
            $table->string('secret')->nullable();
            $table->timestamp('last_used')->nullable();
            $table->boolean('is_primary')->nullable()->default(false);
            $table->boolean('is_enabled')->nullable()->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfaconfigurations');
    }
};
