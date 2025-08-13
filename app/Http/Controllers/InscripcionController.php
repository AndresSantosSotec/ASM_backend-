<?php

namespace App\Http\Controllers;

use App\Models\{Prospecto, EstudiantePrograma};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InscripcionController extends Controller
{
    /**
     * Finaliza la inscripción: actualiza el prospecto y crea los registros de programa.
     */
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
            // 1) Validar que venga el ID del prospecto
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

            // 2) Preparar datos de actualización del prospecto
            $updateData = [
                'nombre_completo' => $data['personales']['nombre']          ?? null,
                'pais_origen'     => $data['personales']['paisOrigen']      ?? null,
                'pais_residencia' => $data['personales']['paisResidencia']  ?? null,
                'telefono'        => $data['personales']['telefono']        ?? null,
                'numero_identificacion' => $data['personales']['dpi']       ?? null,
                'correo_electronico'     => $data['personales']['emailPersonal']    ?? null,
                'correo_corporativo'     => $data['personales']['emailCorporativo'] ?? null,
                'fecha_nacimiento'       => $data['personales']['fechaNacimiento']  ?? null,
                'direccion_residencia'   => $data['personales']['direccion']        ?? null,

                'empresa_donde_labora_actualmente' => $data['laborales']['empresa']             ?? null,
                'puesto'               => $data['laborales']['puesto']              ?? null,
                'telefono_corporativo' => $data['laborales']['telefonoCorporativo'] ?? null,
                'departamento'         => $data['laborales']['departamento']        ?? null,
                'direccion_empresa'    => $data['laborales']['direccionEmpresa']    ?? null,

                'modalidad'            => $data['academicos']['modalidad']             ?? null,
                'fecha_inicio_especifica'  => $data['academicos']['fechaInicioEspecifica'] ?? null,
                // Nombres internos de columnas (ojo: "reduccion" vs "induccion")
                'fecha_taller_reduccion'   => $data['academicos']['fechaTallerInduccion']  ?? null,
                'fecha_taller_integracion' => $data['academicos']['fechaTallerIntegracion'] ?? null,
                'institucion_titulo'       => $data['academicos']['institucionAnterior']   ?? null,
                'anio_graduacion'          => $data['academicos']['añoGraduacion']         ?? null,
                'medio_conocimiento_institucion' => $data['academicos']['medioConocio']    ?? null,
                'cantidad_cursos_aprobados'      => $data['academicos']['cursosAprobados'] ?? null,
                'dia_estudio' => $data['academicos']['diaEstudio'] ?? null,

                'metodo_pago'       => $data['financieros']['formaPago']   ?? null,
                'convenio_pago_id'  => $data['financieros']['convenioId']  ?? null, // <- ID en prospecto
                'monto_inscripcion' => $data['financieros']['inscripcion'] ?? null,

                'status' => 'Pendiente Aprobacion',
            ];

            if (!$prospecto->carnet) {
                $updateData['carnet'] = Prospecto::generateCarnet();
            }

            // 3) Actualiza el prospecto
            $prospecto->update($updateData);

            // 4) Insertar programas (hasta 3 posibles)
            $fechaInicio = $data['academicos']['fechaInicioEspecifica'] ?? null;
            foreach ([1, 2, 3] as $i) {
                $programaId = $data['academicos']["titulo{$i}"] ?? null;
                $duracion   = $data['academicos']["titulo{$i}_duracion"] ?? null;

                if ($programaId && $duracion) {
                    EstudiantePrograma::create([
                        'prospecto_id'   => $prospecto->id,
                        'programa_id'    => $programaId,
                        'convenio_id'    => $data['financieros']['convenioId'] ?? null, // <- ID en programa
                        'fecha_inicio'   => $fechaInicio,
                        'fecha_fin'      => $fechaInicio ? Carbon::parse($fechaInicio)->addMonths((int) $duracion) : null,
                        'duracion_meses' => $duracion,
                        'inscripcion'    => $data['financieros']['inscripcion']     ?? null,
                        'cuota_mensual'  => $data['financieros']['cuotaMensual']     ?? null,
                        'inversion_total' => $data['financieros']['inversionTotal']   ?? null,
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

    /**
     * Muestra la ficha en secciones con compatibilidad:
     * - financieros.convenioId (ID) y financieros.convenioNombre (Nombre)
     * - personales.id (para que finalizar() tenga el ID)
     */
    public function show($id)
    {
        Log::info("⇨ InscripcionController@show — id recibido: {$id}");
        $prospecto = Prospecto::with(['programas', 'convenio'])->find($id);
        if (! $prospecto) {
            Log::warning("⇨ show — Prospecto no existe: {$id}");
            return response()->json(['error' => 'Ficha no encontrada'], 404);
        }

        return response()->json([
            'personales' => [
                'id'           => $prospecto->id, // ← Necesario para finalizar()
                'nombre'       => $prospecto->nombre_completo,
                'paisOrigen'   => $prospecto->pais_origen,
                'paisResidencia' => $prospecto->pais_residencia,
                'telefono'     => $prospecto->telefono,
                'dpi'          => $prospecto->numero_identificacion,
                'emailPersonal' => $prospecto->correo_electronico,
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
                'fechaTallerIntegracion' => $prospecto->fecha_taller_integracion,
                'institucionAnterior'   => $prospecto->institucion_titulo,
                'añoGraduacion'         => $prospecto->anio_graduacion,
                'medioConocio'          => $prospecto->medio_conocimiento_institucion,
                'cursosAprobados'       => $prospecto->cantidad_cursos_aprobados,
                'diaEstudio'            => $prospecto->dia_estudio,
            ],
            'financieros' => [
                'formaPago'        => $prospecto->metodo_pago,
                'convenioId'       => $prospecto->convenio_pago_id, // ← ID para las peticiones
                'convenioNombre'   => optional($prospecto->convenio)->nombre, // ← Nombre para mostrar
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
                'inversion_total' => $p->inversion_total,
                'convenio_id'    => $p->convenio_id,
            ]),
        ]);
    }

    /**
     * Elimina un prospecto y sus programas asociados.
     */
    public function destroy($id)
    {
        Log::info("⇨ InscripcionController@destroy — id recibido: {$id}");

        $prospecto = Prospecto::find($id);
        if (!$prospecto) {
            Log::warning("⇨ destroy — Prospecto no existe: {$id}");
            return response()->json(['error' => 'Ficha no encontrada'], 404);
        }

        $prospecto->programas()->delete();
        $prospecto->delete();

        Log::info("⇨ destroy — Prospecto eliminado: {$id}");
        return response()->json(['message' => 'Ficha eliminada correctamente'], 200);
    }

    /**
     * Carga rápida de prospectos con sus programas.
     */
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
