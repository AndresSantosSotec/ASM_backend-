<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_stages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('flow_id')->nullable();
            $table->integer('stage_order');
            $table->string('title', 120);
            $table->integer('approver_role_id')->nullable();
            $table->string('approver_role_slug', 60)->nullable();
            $table->integer('max_hours')->nullable()->default(24);
            $table->boolean('mandatory')->nullable()->default(true);
            $table->boolean('notify_requester')->nullable()->default(true);
            $table->timestamp('created_at')->nullable()->default(DB::raw("now()"));
            $table->timestamp('updated_at')->nullable()->default(DB::raw("now()"));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_stages');
    }
};
