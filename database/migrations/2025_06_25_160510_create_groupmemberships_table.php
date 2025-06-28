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
        Schema::create('groupmemberships', function (Blueprint $table) {
            $table->integer('group_id');
            $table->integer('member_id');
            $table->enum('member_type', ['user', 'group'])->nullable();
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['group_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groupmemberships');
    }
};
