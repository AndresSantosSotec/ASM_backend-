<?php

namespace App\Http\Controllers;

use App\Models\{Prospecto, EstudiantePrograma};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    
            // Actualiza el prospecto
            $prospecto->update([
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
            ]);
    
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
                        'fecha_fin' => now()->parse($data['academicos']['fechaInicioEspecifica'])->addMonths((int) $duracion),
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
    
}
