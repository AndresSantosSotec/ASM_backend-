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
        Schema::create('securitypolicies', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('password_min_length')->nullable();
            $table->boolean('require_uppercase')->nullable();
            $table->boolean('require_numbers')->nullable();
            $table->boolean('require_special_chars')->nullable();
            $table->boolean('block_common_passwords')->nullable();
            $table->integer('password_expiry_days')->nullable();
            $table->integer('password_history_limit')->nullable();
            $table->integer('notify_before_expiry_days')->nullable();
            $table->boolean('force_change_on_first_login')->nullable();
            $table->integer('failed_attempts_limit')->nullable();
            $table->integer('session_timeout_minutes')->nullable();
            $table->integer('session_max_duration_hours')->nullable();
            $table->integer('max_simultaneous_sessions')->nullable();
            $table->boolean('validate_ip')->nullable();
            $table->boolean('close_inactive_sessions')->nullable();
            $table->boolean('restrict_by_country')->nullable();
            $table->boolean('restrict_by_network')->nullable();
            $table->boolean('restrict_by_device')->nullable();
            $table->boolean('access_hours_enabled')->nullable();
            $table->boolean('notify_unusual_access')->nullable();
            $table->boolean('enforce_2fa')->nullable();
            $table->boolean('enforce_2fa_for_roles')->nullable();
            $table->integer('trusted_device_days')->nullable();
            $table->boolean('captcha_on_failures')->nullable();
            $table->boolean('sso_enabled')->nullable();
            $table->boolean('biometric_auth_enabled')->nullable();
            $table->boolean('email_verification')->nullable();
            $table->boolean('recovery_enabled')->nullable();
            $table->integer('log_retention_days')->nullable();
            $table->string('audit_level', 20)->nullable();
            $table->boolean('real_time_monitoring')->nullable();
            $table->string('report_frequency', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('securitypolicies');
    }
};
