<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactoEnviado;
use Illuminate\Http\Request;
// Importa el Facade de DomPDF
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ContactoEnviadoController extends Controller
{
    /**
     * Descarga el contrato PDF para un envío de contacto específico
     */
    public function downloadContrato($id)
    {
        // 1) Recupera el contacto con su prospecto y firma
        $contacto = ContactoEnviado::with('prospecto.programa', 'creadoPor')
            ->findOrFail($id);

        // 2) Prepara los datos para la vista Blade
        $student     = $contacto->prospecto;
        $programa    = $contacto->prospecto->programa;
        $signature   = $contacto->firma_base64;     // Base64 de la firma
        $advisor     = $contacto->creadoPor;        // Usuario que envió

        // 3) Genera el PDF con la vista 'pdf.confidencialidad'
        $pdf = PDF::loadView('pdf.confidencialidad', [
            'student'     => $student,
            'programa'    => $programa,
            'inscripcion' => $contacto->inscripcion,
            'mensualidad' => $contacto->cuota_mensual,
            'convenio_id' => $contacto->convenio_id,
            'fecha'       => $contacto->created_at->format('d \d\e F \d\e Y'),
            'signature'   => $signature,
            'advisorName' => $advisor->first_name . ' ' . $advisor->last_name,
        ]);

        // 4) Devuelve el PDF como descarga, usando Str::slug para el nombre
        $filename = 'Contrato_' . Str::slug($student->nombre_completo) . '.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Listar todos los contactos enviados
     */
    public function index()
    {
        return ContactoEnviado::with('prospecto')
            ->orderBy('fecha_envio', 'desc')
            ->get();
    }

    /**
     * Guardar un nuevo contacto enviado
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'prospecto_id'  => 'required|exists:prospectos,id',
            'canal'         => 'required|string|max:50',
            'tipo_contacto' => 'nullable|string|max:50',
            'fecha_envio'   => 'nullable|date',
            'resultado'     => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'creado_por'    => 'nullable|integer',
        ]);

        $contacto = ContactoEnviado::create($data);

        return response()->json($contacto, 201);
    }

    /**
     * Mostrar un contacto enviado
     */
    public function show(ContactoEnviado $contactoEnviado)
    {
        return $contactoEnviado->load('prospecto');
    }

    /**
     * Actualizar un contacto enviado
     */
    public function update(Request $request, ContactoEnviado $contactoEnviado)
    {
        $data = $request->validate([
            'canal'         => 'sometimes|required|string|max:50',
            'tipo_contacto' => 'sometimes|nullable|string|max:50',
            'fecha_envio'   => 'sometimes|nullable|date',
            'resultado'     => 'sometimes|nullable|string|max:100',
            'observaciones' => 'sometimes|nullable|string',
        ]);

        $contactoEnviado->update($data);

        return response()->json($contactoEnviado);
    }

    /**
     * Eliminar un contacto enviado
     */
    public function destroy(ContactoEnviado $contactoEnviado)
    {
        $contactoEnviado->delete();
        return response()->json(null, 204);
    }

    /**
     * Listar contactos por prospecto
     */
    public function byProspecto($prospectoId)
    {
        return ContactoEnviado::where('prospecto_id', $prospectoId)
            ->orderBy('fecha_envio', 'desc')
            ->get();
    }

    /**
     * Listar los contactos enviados hoy
     */
    public function today()
    {
        $hoy = now()->toDateString();
        $list = ContactoEnviado::with('prospecto')
            ->whereDate('fecha_envio', $hoy)
            ->orderBy('fecha_envio', 'desc')
            ->get();

        return response()->json($list, 200);
    }
}
