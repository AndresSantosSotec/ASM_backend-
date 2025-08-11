<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeDatecitaToDatetimeOnTbcitasTable extends Migration
{
    public function up()
    {
        Schema::table('tbcitas', function (Blueprint $table) {
            // Convierte de DATE a DATETIME (almacena fecha + hora)
            $table->dateTime('datecita')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('tbcitas', function (Blueprint $table) {
            // Reviertes al tipo DATE original
            $table->date('datecita')->nullable()->change();
        });
    }
}
