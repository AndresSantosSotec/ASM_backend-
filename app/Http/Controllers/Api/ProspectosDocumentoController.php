<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProspectosDocumento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProspectosDocumentoController extends Controller
{
    public function index()
    {
        // Lista todos con prospecto cargado
        return response()->json(
            ProspectosDocumento::with('prospecto')->get()
        );
    }

    public function store(Request $request)
    {
        // Validaciones
        $data = $request->validate([
            'prospecto_id'   => 'required|exists:prospectos,id',
            'tipo_documento' => 'required|string|max:100',
            'file'           => 'required|file|mimes:pdf,jpg,png|max:5120',
        ]);

        // Guardar archivo en disco 'public'
        $path = $request->file('file')
                        ->store('prospectos/'.$data['prospecto_id'], 'public');

        // Crear registro
        $doc = ProspectosDocumento::create([
            'prospecto_id'   => $data['prospecto_id'],
            'tipo_documento' => $data['tipo_documento'],
            'ruta_archivo'   => $path,
            'subida_at'      => now(),
            'created_by'     => Auth::id(),
        ]);

        return response()->json($doc, 201);
    }

    public function show($id)
    {
        return response()->json(
            ProspectosDocumento::findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $doc = ProspectosDocumento::findOrFail($id);

        $data = $request->validate([
            'tipo_documento' => 'sometimes|string|max:100',
            'file'           => 'sometimes|file|mimes:pdf,jpg,png|max:5120',
        ]);

        // Si hay nuevo archivo, borra el anterior y guarda el nuevo
        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->ruta_archivo = $request->file('file')
                                        ->store('prospectos/'.$doc->prospecto_id, 'public');
        }

        // Actualiza tipo si llegó
        if (isset($data['tipo_documento'])) {
            $doc->tipo_documento = $data['tipo_documento'];
        }

        $doc->updated_by = Auth::id();
        $doc->save();

        return response()->json($doc);
    }

    public function destroy($id)
    {
        $doc = ProspectosDocumento::findOrFail($id);
        $doc->deleted_by = Auth::id();
        $doc->save();
        $doc->delete();

        return response()->noContent();
    }
}
