<?php

namespace App\Http\Controllers;

use App\Models\{Prospecto, EstudiantePrograma};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InscripcionController extends Controller
{
    public function finalizar(Request $request)
    {
        Log::info('Datos recibidos en inscripción:', $request->all());

        $data = $request->validate([
            'personales'  => 'required|array',
            'laborales'   => 'required|array',
            'academicos'  => 'required|array',
            'financieros' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            // Validar que venga el ID del prospecto
            if (empty($data['personales']['id'])) {
                return response()->json([
                    'error' => 'Debes seleccionar un prospecto existente.',
                ], 422);
            }

            $prospecto = Prospecto::find($data['personales']['id']);

            if (!$prospecto) {
                return response()->json([
                    'error' => 'Prospecto no encontrado.',
                ], 404);
            }

            // Preparar datos de actualización
            $updateData = [
                'nombre_completo' => $data['personales']['nombre'],
                'pais_origen'     => $data['personales']['paisOrigen'],
                'pais_residencia' => $data['personales']['paisResidencia'],
                'telefono'        => $data['personales']['telefono'],
                'numero_identificacion' => $data['personales']['dpi'],
                'correo_electronico'     => $data['personales']['emailPersonal'],
                'correo_corporativo'     => $data['personales']['emailCorporativo'],
                'fecha_nacimiento'       => $data['personales']['fechaNacimiento'],
                'direccion_residencia'   => $data['personales']['direccion'],
                'empresa_donde_labora_actualmente' => $data['laborales']['empresa'],
                'puesto'               => $data['laborales']['puesto'],
                'telefono_corporativo' => $data['laborales']['telefonoCorporativo'],
                'departamento'         => $data['laborales']['departamento'],
                'direccion_empresa'    => $data['laborales']['direccionEmpresa'],
                'modalidad'            => $data['academicos']['modalidad'],
                'fecha_inicio_especifica'  => $data['academicos']['fechaInicioEspecifica'],
                'fecha_taller_reduccion'   => $data['academicos']['fechaTallerInduccion'],
                'fecha_taller_integracion' => $data['academicos']['fechaTallerIntegracion'],
                'institucion_titulo'       => $data['academicos']['institucionAnterior'],
                'anio_graduacion'          => $data['academicos']['añoGraduacion'],
                'medio_conocimiento_institucion' => $data['academicos']['medioConocio'],
                'cantidad_cursos_aprobados'      => $data['academicos']['cursosAprobados'],
                'dia_estudio' => $data['academicos']['diaEstudio'],
                'metodo_pago' => $data['financieros']['formaPago'],
                'convenio_pago_id' => $data['financieros']['convenioId'] ?? null,
                'monto_inscripcion' => $data['financieros']['inscripcion'],
                'status' => 'Pendiente Aprobacion', // 👈 AÑADIDO AQUÍ
            ];

            if (!$prospecto->carnet) {
                $updateData['carnet'] = Prospecto::generateCarnet();
            }

            // Actualiza el prospecto
            $prospecto->update($updateData);

            // Insertar programas
            foreach ([1, 2, 3] as $i) {
                $programaId = $data['academicos']["titulo{$i}"] ?? null;
                $duracion   = $data['academicos']["titulo{$i}_duracion"] ?? null;

                if ($programaId && $duracion) {
                    EstudiantePrograma::create([
                        'prospecto_id' => $prospecto->id,
                        'programa_id' => $programaId,
                        'convenio_id' => $data['financieros']['convenioId'] ?? null,
                        'fecha_inicio' => $data['academicos']['fechaInicioEspecifica'],
                        'fecha_fin' => Carbon::parse($data['academicos']['fechaInicioEspecifica'])->addMonths((int) $duracion),
                        'duracion_meses' => $duracion,
                        'inscripcion' => $data['financieros']['inscripcion'],
                        'cuota_mensual' => $data['financieros']['cuotaMensual'],
                        'inversion_total' => $data['financieros']['inversionTotal'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Inscripción finalizada correctamente',
                'prospecto_id' => $prospecto->id,
                'programas' => EstudiantePrograma::where('prospecto_id', $prospecto->id)->get(['id']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al guardar inscripción', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'input'   => $data,
            ]);

            return response()->json([
                'error' => 'Error al guardar la inscripción',
                'message' => $e->getMessage(),
                'data_enviada' => $data,
            ], 500);
        }
    }

    // app/Http/Controllers/InscripcionController.php
    public function show($id)
    {
        Log::info("⇨ InscripcionController@show — id recibido: {$id}");
        $prospecto = Prospecto::with('programas')->find($id);
        if (! $prospecto) {
            Log::warning("⇨ show — Prospecto no existe: {$id}");
            return response()->json(['error' => 'Ficha no encontrada'], 404);
        }


        // Estructuramos la respuesta en secciones
        return response()->json([
            'personales' => [
                'nombre'       => $prospecto->nombre_completo,
                'paisOrigen'   => $prospecto->pais_origen,
                'paisResidencia' => $prospecto->pais_residencia,
                'telefono'     => $prospecto->telefono,
                'dpi'          => $prospecto->numero_identificacion,
                'emailPersonal'=> $prospecto->correo_electronico,
                'emailCorporativo' => $prospecto->correo_corporativo,
                'fechaNacimiento'  => $prospecto->fecha_nacimiento,
                'direccion'    => $prospecto->direccion_residencia,
            ],
            'laborales' => [
                'empresa'             => $prospecto->empresa_donde_labora_actualmente,
                'puesto'              => $prospecto->puesto,
                'telefonoCorporativo' => $prospecto->telefono_corporativo,
                'departamento'        => $prospecto->departamento,
                'direccionEmpresa'    => $prospecto->direccion_empresa,
            ],
            'academicos' => [
                'modalidad'             => $prospecto->modalidad,
                'fechaInicioEspecifica' => $prospecto->fecha_inicio_especifica,
                'fechaTallerInduccion'  => $prospecto->fecha_taller_reduccion,
                'fechaTallerIntegracion'=> $prospecto->fecha_taller_integracion,
                'institucionAnterior'   => $prospecto->institucion_titulo,
                'añoGraduacion'         => $prospecto->anio_graduacion,
                'medioConocio'          => $prospecto->medio_conocimiento_institucion,
                'cursosAprobados'       => $prospecto->cantidad_cursos_aprobados,
                'diaEstudio'            => $prospecto->dia_estudio,
            ],
            'financieros' => [
                'formaPago'        => $prospecto->metodo_pago,
                'convenioId'       => $prospecto->convenio_pago_id,
                'inscripcion'      => $prospecto->monto_inscripcion,
                'cuotaMensual'     => optional($prospecto->programas->first())->cuota_mensual,
                'inversionTotal'   => optional($prospecto->programas->first())->inversion_total,
            ],
            'programas' => $prospecto->programas->map(fn($p) => [
                'id'             => $p->programa_id,
                'fecha_inicio'   => $p->fecha_inicio,
                'fecha_fin'      => $p->fecha_fin,
                'duracion_meses' => $p->duracion_meses,
                'inscripcion'    => $p->inscripcion,
                'cuota_mensual'  => $p->cuota_mensual,
                'inversion_total'=> $p->inversion_total,
            ]),
        ]);
    }

    public function destroy($id)
    {
        Log::info("⇨ InscripcionController@destroy — id recibido: {$id}");
        $prospecto = Prospecto::find($id);
        if (!$prospecto) {
            Log::warning("⇨ destroy — Prospecto no existe: {$id}");
            return response()->json(['error' => 'Ficha no encontrada'], 404);
        }

        // Eliminar programas asociados
        $prospecto->programas()->delete();

        // Eliminar el prospecto
        $prospecto->delete();

        Log::info("⇨ destroy — Prospecto eliminado: {$id}");
        return response()->json(['message' => 'Ficha eliminada correctamente'], 200);
    }

    public function Loader()
    {
        Log::info('⇨ InscripcionController@Loader — Cargando datos de inscripción');

        $prospectos = Prospecto::with('programas')->get();

        if ($prospectos->isEmpty()) {
            Log::warning('⇨ Loader — No se encontraron prospectos');
            return response()->json(['message' => 'No hay prospectos registrados'], 404);
        }

        return response()->json($prospectos, 200);
    }
}
