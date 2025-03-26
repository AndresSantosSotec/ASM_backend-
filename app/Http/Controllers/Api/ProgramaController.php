<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Programa;

class ProgramaController extends Controller
{
    public function ObtenerProgramas()
    {
            // Obtén todos los programas de la tabla
            $programas = Programa::select('id', 'abreviatura', 'nombre_del_programa', 'meses')->get();
            // Devuelve los datos en formato JSON
            return response()->json($programas);
    }

    public function CretatePrograma(Request $request)
    {
        // Valida los datos del formulario
        $request->validate([
            'abreviatura' => 'required|max:10',
            'nombre_del_programa' => 'required|max:100',
            'meses' => 'required|integer'
        ]);

        // Crea un nuevo programa
        $programa = Programa::create($request->all());

        // Devuelve los datos en formato JSON
        return response()->json($programa, 201);
    }

    public function UpdatePrograma(Request $request, $id)
    {
        // Valida los datos del formulario
        $request->validate([
            'abreviatura' => 'required|max:10',
            'nombre_del_programa' => 'required|max:100',
            'meses' => 'required|integer'
        ]);

        // Busca el programa por ID
        $programa = Programa::find($id);

        // Si no se encuentra el programa, devuelve un error 404
        if (!$programa) {
            return response()->json(['error' => 'Programa no encontrado'], 404);
        }

        // Actualiza los datos del programa
        $programa->update($request->all());

        // Devuelve los datos en formato JSON
        return response()->json($programa);
    }

    public function deletePrograma($id)
    {
        // Busca el programa por ID
        $programa = Programa::find($id);

        // Si no se encuentra el programa, devuelve un error 404
        if (!$programa) {
            return response()->json(['error' => 'Programa no encontrado'], 404);
        }

        // Elimina el programa
        $programa->delete();

        // Devuelve un mensaje de éxito
        return response()->json(['message' => 'Programa eliminado']);
    }


}
