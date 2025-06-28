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
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 50)->unique('users_username_key');
            $table->string('email', 100)->unique('users_email_key');
            $table->string('password_hash');
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->boolean('is_active')->nullable()->default(true);
            $table->boolean('email_verified')->nullable()->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('last_login')->nullable();
            $table->boolean('mfa_enabled')->nullable()->default(false);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
