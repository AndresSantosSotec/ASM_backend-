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
        Schema::create('prospectos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->string('nombre_completo');
            $table->string('telefono');
            $table->string('correo_electronico');
            $table->string('genero');
            $table->string('empresa_donde_labora_actualmente')->nullable();
            $table->string('puesto')->nullable();
            $table->text('notas_generales')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('interes')->nullable();
            $table->string('nota1')->nullable();
            $table->string('nota2')->nullable();
            $table->string('nota3')->nullable();
            $table->text('cierre')->nullable();
            $table->timestamps();
            $table->string('status')->nullable();
            $table->string('correo_corporativo')->nullable();
            $table->string('pais_origen', 100)->nullable();
            $table->string('pais_residencia', 100)->nullable();
            $table->string('numero_identificacion', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('modalidad', 50)->nullable();
            $table->date('fecha_inicio_especifica')->nullable();
            $table->date('fecha_taller_reduccion')->nullable();
            $table->date('fecha_taller_integracion')->nullable();
            $table->string('medio_conocimiento_institucion')->nullable();
            $table->string('metodo_pago', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->string('direccion_residencia')->nullable();
            $table->string('telefono_corporativo', 50)->nullable();
            $table->string('direccion_empresa')->nullable();
            $table->string('ultimo_titulo_obtenido')->nullable();
            $table->string('institucion_titulo')->nullable();
            $table->smallInteger('anio_graduacion')->nullable();
            $table->integer('cantidad_cursos_aprobados')->nullable();
            $table->decimal('monto_inscripcion', 10)->nullable();
            $table->bigInteger('convenio_pago_id')->nullable();
            $table->string('dia_estudio', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospectos');
    }
};
