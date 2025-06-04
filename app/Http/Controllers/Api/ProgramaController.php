<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Programa;
use App\Models\PrecioPrograma;

class ProgramaController extends Controller
{
    public function ObtenerProgramas()
    {
        // Obtén todos los programas con sus precios asociados
        $programas = Programa::with('precios')->get([
            'id', 
            'abreviatura', 
            'nombre_del_programa', 
            'meses'
        ]);
        
        return response()->json($programas);
    }

    public function CretatePrograma(Request $request)
    {
        // Valida los datos del formulario
        $request->validate([
            'abreviatura' => 'required|max:10',
            'nombre_del_programa' => 'required|max:100',
            'meses' => 'required|integer',
            'precios' => 'sometimes|array',
            'precios.inscripcion' => 'required_with:precios|numeric',
            'precios.cuota_mensual' => 'required_with:precios|numeric',
        ]);

        // Crea un nuevo programa
        $programa = Programa::create($request->except('precios'));

        // Si se enviaron precios, los creamos
        if ($request->has('precios')) {
            $precioData = $request->input('precios');
            $precioData['programa_id'] = $programa->id;
            $precioData['meses'] = $programa->meses;
            
            $programa->precios()->create($precioData);
            
            // Recargamos la relación para incluir los precios en la respuesta
            $programa->load('precios');
        }

        return response()->json($programa, 201);
    }

    public function UpdatePrograma(Request $request, $id)
    {
        // Valida los datos del formulario
        $request->validate([
            'abreviatura' => 'required|max:10',
            'nombre_del_programa' => 'required|max:100',
            'meses' => 'required|integer',
            'precios' => 'sometimes|array',
            'precios.inscripcion' => 'required_with:precios|numeric',
            'precios.cuota_mensual' => 'required_with:precios|numeric',
        ]);

        // Busca el programa por ID
        $programa = Programa::find($id);

        if (!$programa) {
            return response()->json(['error' => 'Programa no encontrado'], 404);
        }

        // Actualiza los datos del programa
        $programa->update($request->except('precios'));

        // Si se enviaron precios, los actualizamos o creamos
        if ($request->has('precios')) {
            $precioData = $request->input('precios');
            $precioData['meses'] = $programa->meses;
            
            if ($programa->precios()->exists()) {
                $programa->precios()->update($precioData);
            } else {
                $precioData['programa_id'] = $programa->id;
                $programa->precios()->create($precioData);
            }
            
            // Recargamos la relación para incluir los precios en la respuesta
            $programa->load('precios');
        }

        return response()->json($programa);
    }

    public function deletePrograma($id)
    {
        // Busca el programa por ID (los precios se eliminarán en cascada por la FK)
        $programa = Programa::find($id);

        if (!$programa) {
            return response()->json(['error' => 'Programa no encontrado'], 404);
        }

        $programa->delete();

        return response()->json(['message' => 'Programa y sus precios asociados eliminados']);
    }

    // Métodos específicos para manejar precios
    
    public function obtenerPreciosPrograma($programaId)
    {
        $programa = Programa::with('precios')->find($programaId);
        
        if (!$programa) {
            return response()->json(['error' => 'Programa no encontrado'], 404);
        }
        
        return response()->json($programa->precios);
    }

    public function actualizarPrecioPrograma(Request $request, $programaId)
    {
        $request->validate([
            'inscripcion' => 'required|numeric',
            'cuota_mensual' => 'required|numeric',
        ]);

        $programa = Programa::find($programaId);
        
        if (!$programa) {
            return response()->json(['error' => 'Programa no encontrado'], 404);
        }

        $precioData = $request->all();
        $precioData['meses'] = $programa->meses;
        
        if ($programa->precios()->exists()) {
            $programa->precios()->update($precioData);
        } else {
            $precioData['programa_id'] = $programa->id;
            $programa->precios()->create($precioData);
        }
        
        return response()->json(['message' => 'Precios actualizados correctamente']);
    }
}