<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospecto;
use App\Models\ProspectosDocumento;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendContractPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\ContactoEnviado;


class ProspectoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $isAdmin = strtolower($user->rol) === 'administrador';

        // ◀ Aquí agregamos, además de 'creator', el eager loading de:
        //    – ‘programas.programa’ para traer en cada EstudiantePrograma su Programa
        //    – ‘courses’ para traer los cursos asociados (vía pivote curso_prospecto)
        $query = Prospecto::with([
            'creator',
            'programas.programa',
            'courses'
        ]);

        if (!$isAdmin) {
            $query->where('created_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $prospectos = $query->get();

        return response()->json([
            'message' => 'Datos de prospectos obtenidos con éxito',
            'data'    => $prospectos,
        ]);
    }


    public function filterByStatus($status)
    {
        $prospectos = Prospecto::with([
            'creator',
            'programas.programa',
            'courses'
        ])
            ->where('status', $status)
            ->get();

        return response()->json([
            'message' => "Prospectos en estado “{$status}” obtenidos",
            'data'    => $prospectos,
        ]);
    }


    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $v = $request->validate([
            'fecha' => 'required|date',
            'nombreCompleto' => 'required|string',
            'telefono' => 'required|string',
            'correoElectronico' => 'required|email|unique:prospectos,correo_electronico', // Agregada validación unique
            'genero' => 'required|string',
            'empresaDondeLaboraActualmente' => 'nullable|string',
            'puesto' => 'nullable|string',
            'notasGenerales' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'interes' => 'nullable|string',
            'status' => 'nullable|string',
            'nota1' => 'nullable|string',
            'nota2' => 'nullable|string',
            'nota3' => 'nullable|string',
            'cierre' => 'nullable|string',
            'departamento' => 'required|string',
            'municipio' => 'required|string',
            'correoCorporativo' => 'nullable|email',
            'numeroIdentificacion' => 'nullable|string|max:20',
            'fechaNacimiento' => 'nullable|date',
            'direccionResidencia' => 'nullable|string',
            'telefonoCorporativo' => 'nullable|string',
            'direccionEmpresa' => 'nullable|string',
            'ultimoTituloObtenido' => 'nullable|string',
            'institucionTitulo' => 'nullable|string',
            'anioGraduacion' => 'nullable|integer',
            'cantidadCursosAprobados' => 'nullable|integer',
            'modalidad' => 'nullable|string',
            'fechaInicioEspecifica' => 'nullable|date',
            'fechaTallerReduccion' => 'nullable|date',
            'fechaTallerIntegracion' => 'nullable|date',
            'medioConocimientoInstitucion' => 'nullable|string',
            'metodoPago' => 'nullable|string',
            'diaEstudio' => 'nullable|string|max:20',
        ]);

        $prospecto = Prospecto::create([
            'fecha' => $v['fecha'],
            'nombre_completo' => $v['nombreCompleto'],
            'telefono' => $v['telefono'],
            'correo_electronico' => $v['correoElectronico'],
            'genero' => $v['genero'],
            'empresa_donde_labora_actualmente' => $v['empresaDondeLaboraActualmente'] ?? null,
            'puesto' => $v['puesto'] ?? null,
            'notas_generales' => $v['notasGenerales'] ?? null,
            'observaciones' => $v['observaciones'] ?? null,
            'interes' => $v['interes'] ?? null,
            'status' => $v['status'] ?? 'En seguimiento',
            'nota1' => $v['nota1'] ?? null,
            'nota2' => $v['nota2'] ?? null,
            'nota3' => $v['nota3'] ?? null,
            'cierre' => $v['cierre'] ?? null,
            'departamento' => $v['departamento'],
            'municipio' => $v['municipio'],
            'correo_corporativo' => $v['correoCorporativo'] ?? null,
            'numero_identificacion' => $v['numeroIdentificacion'] ?? null,
            'fecha_nacimiento' => $v['fechaNacimiento'] ?? null,
            'direccion_residencia' => $v['direccionResidencia'] ?? null,
            'telefono_corporativo' => $v['telefonoCorporativo'] ?? null,
            'direccion_empresa' => $v['direccionEmpresa'] ?? null,
            'ultimo_titulo_obtenido' => $v['ultimoTituloObtenido'] ?? null,
            'institucion_titulo' => $v['institucionTitulo'] ?? null,
            'anio_graduacion' => $v['anioGraduacion'] ?? null,
            'cantidad_cursos_aprobados' => $v['cantidadCursosAprobados'] ?? null,
            'modalidad' => $v['modalidad'] ?? null,
            'fecha_inicio_especifica' => $v['fechaInicioEspecifica'] ?? null,
            'fecha_taller_reduccion' => $v['fechaTallerReduccion'] ?? null,
            'fecha_taller_integracion' => $v['fechaTallerIntegracion'] ?? null,
            'medio_conocimiento_institucion' => $v['medioConocimientoInstitucion'] ?? null,
            'metodo_pago' => $v['metodoPago'] ?? null,
            'dia_estudio' => $v['diaEstudio'] ?? null,
            'created_by' => $user->id,
        ]);

        $prospecto->load('creator');

        return response()->json([
            'message' => 'Prospecto guardado con éxito',
            'data' => $prospecto,
        ], 201);
    }

    public function show($id)
    {
        // Al obtener un solo prospecto, también cargamos las mismas relaciones
        $prospecto = Prospecto::with([
            'creator',
            'programas.programa',
            'courses'
        ])->find($id);

        if (!$prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        return response()->json([
            'message' => 'Prospecto obtenido con éxito',
            'data'    => $prospecto,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $prospecto = Prospecto::find($id);
        if (!$prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        $v = $request->validate([
            'fecha' => 'nullable|date',
            'nombreCompleto' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correoElectronico' => 'nullable|email',
            'genero' => 'nullable|string',
            'empresaDondeLaboraActualmente' => 'nullable|string',
            'puesto' => 'nullable|string',
            'notasGenerales' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'interes' => 'nullable|string',
            'status' => 'nullable|string',
            'nota1' => 'nullable|string',
            'nota2' => 'nullable|string',
            'nota3' => 'nullable|string',
            'cierre' => 'nullable|string',
            'departamento' => 'nullable|string',
            'municipio' => 'nullable|string',
            'correoCorporativo' => 'nullable|email',
            'numeroIdentificacion' => 'nullable|string|max:20',
            'fechaNacimiento' => 'nullable|date',
            'direccionResidencia' => 'nullable|string',
            'telefonoCorporativo' => 'nullable|string',
            'direccionEmpresa' => 'nullable|string',
            'ultimoTituloObtenido' => 'nullable|string',
            'institucionTitulo' => 'nullable|string',
            'anioGraduacion' => 'nullable|integer',
            'cantidadCursosAprobados' => 'nullable|integer',
            'modalidad' => 'nullable|string',
            'fechaInicioEspecifica' => 'nullable|date',
            'fechaTallerReduccion' => 'nullable|date',
            'fechaTallerIntegracion' => 'nullable|date',
            'medioConocimientoInstitucion' => 'nullable|string',
            'metodoPago' => 'nullable|string',
            'diaEstudio' => 'nullable|string|max:20',
        ]);

        foreach ($v as $field => $value) {
            $attr = match ($field) {
                'nombreCompleto' => 'nombre_completo',
                'correoElectronico' => 'correo_electronico',
                'empresaDondeLaboraActualmente' => 'empresa_donde_labora_actualmente',
                'correoCorporativo' => 'correo_corporativo',
                'numeroIdentificacion' => 'numero_identificacion',
                'fechaNacimiento' => 'fecha_nacimiento',
                'direccionResidencia' => 'direccion_residencia',
                'telefonoCorporativo' => 'telefono_corporativo',
                'direccionEmpresa' => 'direccion_empresa',
                'ultimoTituloObtenido' => 'ultimo_titulo_obtenido',
                'institucionTitulo' => 'institucion_titulo',
                'anioGraduacion' => 'anio_graduacion',
                'cantidadCursosAprobados' => 'cantidad_cursos_aprobados',
                'modalidad' => 'modalidad',
                'fechaInicioEspecifica' => 'fecha_inicio_especifica',
                'fechaTallerReduccion' => 'fecha_taller_reduccion',
                'fechaTallerIntegracion' => 'fecha_taller_integracion',
                'medioConocimientoInstitucion' => 'medio_conocimiento_institucion',
                'metodoPago' => 'metodo_pago',
                'diaEstudio' => 'dia_estudio',
                default => $field,
            };
            $prospecto->$attr = $value;
        }

        $prospecto->updated_by = $user->id;
        $prospecto->save();
        $prospecto->load('creator');

        return response()->json([
            'message' => 'Prospecto actualizado con éxito',
            'data' => $prospecto,
        ]);
    }

    public function destroy($id)
    {
        $prospecto = Prospecto::with('documentos')->find($id);
        if (!$prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        $userId = auth()->id();

        DB::transaction(function () use ($prospecto, $userId) {
            foreach ($prospecto->documentos as $doc) {
                $doc->deleted_by = $userId;
                $doc->save();
                // Eliminar definitivamente para evitar violaciones de FK
                $doc->forceDelete();
            }

            $prospecto->deleted_by = $userId;
            $prospecto->save();
            $prospecto->delete();
        });

        return response()->json(['message' => 'Prospecto eliminado con éxito']);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $v = $request->validate([
            'status' => 'required|string',
        ]);

        $prospecto = Prospecto::find($id);
        if (!$prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        $prospecto->status = $v['status'];
        $prospecto->updated_by = $user->id;
        $prospecto->save();

        return response()->json([
            'message' => 'Estado del prospecto actualizado con éxito',
            'data' => $prospecto,
        ]);
    }

    public function assignOne(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $data = $request->validate([
            'created_by' => 'required|integer|exists:users,id',
        ]);

        try {
            $prospecto = Prospecto::findOrFail($id);
            $prospecto->update([
                'created_by' => $data['created_by'],
                'updated_by' => $user->id,
            ]);

            $prospecto->load('creator');

            return response()->json([
                'message' => 'Prospecto reasignado correctamente',
                'data'    => $prospecto,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error reasignando prospecto: ' . $e->getMessage(), [
                'prospecto_id' => $id,
                'user_id'      => $user->id,
                'input'        => $request->all(),
                'stack'        => $e->getTraceAsString()
            ]);

            return response()->json([
                'error'   => 'Ocurrió un error al reasignar el prospecto.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'prospecto_ids' => 'required|array',
            'prospecto_ids.*' => 'integer|exists:prospectos,id',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        Prospecto::whereIn('id', $data['prospecto_ids'])
            ->update([
                'created_by' => $data['created_by'],
                'updated_by' => auth()->id(),
            ]);

        return response()->json(['message' => 'Prospectos reasignados correctamente']);
    }

    public function bulkUpdateStatus(Request $request)
    {
        $data = $request->validate([
            'prospecto_ids' => 'required|array',
            'prospecto_ids.*' => 'integer|exists:prospectos,id',
            'status' => 'required|string',
        ]);

        Prospecto::whereIn('id', $data['prospecto_ids'])
            ->update([
                'status' => $data['status'],
                'updated_by' => auth()->id(),
            ]);

        return response()->json(['message' => 'Estado de prospectos actualizado correctamente']);
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'prospecto_ids' => 'required|array',
            'prospecto_ids.*' => 'integer|exists:prospectos,id',
        ]);

        $userId = auth()->id();

        DB::transaction(function () use ($data, $userId) {
            $docs = ProspectosDocumento::whereIn('prospecto_id', $data['prospecto_ids']);
            $docs->update(['deleted_by' => $userId]);
            // Eliminación definitiva de documentos para evitar violaciones de FK
            $docs->forceDelete();

            Prospecto::whereIn('id', $data['prospecto_ids'])
                ->update(['deleted_by' => $userId]);

            Prospecto::whereIn('id', $data['prospecto_ids'])->delete();
        });

        return response()->json(['message' => 'Prospectos eliminados correctamente']);
    }

    public function enviarContrato(Request $request, $id)
    {
        try {
            $request->validate([
                'signature' => 'required|string',
            ]);

            // Prospecto y programa
            $prospecto = Prospecto::findOrFail($id);
            $estProg   = $prospecto->programas()
                ->whereNull('deleted_at')
                ->latest('created_at')
                ->with('programa')
                ->firstOrFail();

            // Fecha formateada
            $fecha = now()
                ->locale('es_GT')
                ->isoFormat('dddd, D [de] MMMM [de] YYYY');
            $fecha = ucfirst($fecha);

            // Asesor autenticado
            $advisor = $request->user();
            $advisorName = "{$advisor->first_name} {$advisor->last_name}";

            // Generar PDF
            $pdf = PDF::loadView('pdf.contrato', [
                'student'      => $prospecto,
                'programa'     => $estProg->programa,
                'inscripcion'  => $estProg->inscripcion,
                'mensualidad'  => $estProg->cuota_mensual,
                'convenio_id'  => $estProg->convenio_id,
                'fecha'        => $fecha,
                'signature'    => $request->signature,
                'advisorName'  => $advisorName,
            ]);
            $pdfData = $pdf->output();

            // Enviar correo al prospecto y en copia al asesor
            Mail::to($prospecto->correo_electronico)
                ->cc($advisor->email)
                ->send(new SendContractPdf(
                    $prospecto,
                    $pdfData,
                    $fecha,
                    $estProg->programa,
                    $estProg->inscripcion,
                    $estProg->cuota_mensual,
                    $estProg->convenio_id,
                    $advisorName,
                    $request->signature
                ));

            // ——— Aquí guardamos el registro en contactos_enviados ———
            ContactoEnviado::create([
                'prospecto_id'   => $prospecto->id,
                'canal'          => 'email',
                'tipo_contacto'  => 'enviar-contrato',
                'fecha_envio'    => now(),
                'resultado'      => 'enviado',
                'observaciones'  => null,
                'creado_por'     => $advisor->id,
            ]);

            return response()->json([
                'message' => "Contrato enviado a {$prospecto->correo_electronico}",
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en enviarContrato: ' . $e->getMessage(), [
                'id'    => $id,
                'input' => $request->all(),
                'stack' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error'   => 'No se pudo enviar el contrato.',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function pendientesAprobacion()
    {
        Log::info("⇨ ProspectoController@pendientesAprobacion — inicio");

        $subquery = DB::table('estudiante_programa')
            ->select('prospecto_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('prospecto_id');

        Log::info("⇨ pendientesAprobacion — subquery preparado");

        $prospectos = DB::table('prospectos AS p')
            ->joinSub($subquery, 'latest_ep', function ($join) {
                $join->on('p.id', '=', 'latest_ep.prospecto_id');
            })
            ->join('estudiante_programa AS ep', 'ep.id', '=', 'latest_ep.max_id')
            ->join('tb_programas AS pr', 'ep.programa_id', '=', 'pr.id')
            ->select(
                'p.id',
                'p.nombre_completo',
                'p.telefono',
                'p.correo_electronico',
                'p.departamento',
                'p.status',
                'pr.nombre_del_programa AS nombre_programa',
                DB::raw('(SELECT COUNT(*) FROM estudiante_programa WHERE prospecto_id = p.id) AS cantidad_programas')
            )
            ->where('p.status', 'Pendiente Aprobacion')
            ->get();

        Log::info("⇨ pendientesAprobacion — encontrados: {$prospectos->count()} prospectos");

        return response()->json(['data' => $prospectos]);
    }

    public function pendientesConDocs()
    {
        // sólo status = Pendiente Aprobacion
        $prospectos = Prospecto::with('documentos')
            ->where('status', 'Pendiente Aprobacion')
            ->get();

        return response()->json([
            'message' => 'Prospectos pendientes con sus documentos',
            'data'    => $prospectos
        ]);
    }

    /**
     * Obtener prospectos inscritos con sus programas y cursos asociados
     */
    public function inscritosConCursos(Request $request)
    {
        $perPage = $request->integer('per_page', 50);

        $prospectos = Prospecto::with('programas.programa.courses')
            ->where('status', 'Inscrito')
            ->whereHas('programas')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Prospectos inscritos con programas y cursos',
            'data'    => $prospectos->items(),
            'links'   => [
                'first' => $prospectos->url(1),
                'last'  => $prospectos->url($prospectos->lastPage()),
                'prev'  => $prospectos->previousPageUrl(),
                'next'  => $prospectos->nextPageUrl(),
            ],
            'meta'    => [
                'current_page' => $prospectos->currentPage(),
                'from'         => $prospectos->firstItem(),
                'last_page'    => $prospectos->lastPage(),
                'path'         => $prospectos->path(),
                'per_page'     => $prospectos->perPage(),
                'to'           => $prospectos->lastItem(),
                'total'        => $prospectos->total(),
            ],
        ]);
    }

    public function downloadContrato($id)
    {
        $prospecto = Prospecto::findOrFail($id);
        // Genera o recupera el PDF tal como en enviarContrato…
        $pdf = PDF::loadView('pdf.contrato', [/* … mismos datos … */]);
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Contrato.pdf"');
    }

    public function programasAsignados(Request $request)
    {
        $nombre = $request->input('nombre_completo');
        $carnet = $request->input('carnet');

        $prospecto = Prospecto::where('nombre_completo', $nombre)
            ->where('carnet', $carnet)
            ->where('activo', true)
            ->where('status', 'Inscrito')
            ->first();

        if (!$prospecto) {
            return response()->json(['message' => 'Estudiante no encontrado o no cumple criterios'], 404);
        }

        $programas = EstudiantePrograma::with(['programa', 'convenio', 'cuotas', 'pagos'])
            ->where('prospecto_id', $prospecto->id)
            ->get();

        return response()->json([
            'estudiante' => $prospecto->only(['id', 'nombre_completo', 'carnet']),
            'programas' => $programas
        ]);
    }
}
