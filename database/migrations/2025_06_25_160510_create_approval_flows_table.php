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
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120);
            $table->string('code', 40)->unique('approval_flows_code_key');
            $table->text('description')->nullable();
            $table->string('scope', 30);
            $table->boolean('is_active')->nullable()->default(true);
            $table->boolean('parallel')->nullable()->default(false);
            $table->boolean('auto_escalate')->nullable()->default(false);
            $table->timestamp('created_at')->nullable()->default(DB::raw("now()"));
            $table->timestamp('updated_at')->nullable()->default(DB::raw("now()"));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_flows');
    }
};
