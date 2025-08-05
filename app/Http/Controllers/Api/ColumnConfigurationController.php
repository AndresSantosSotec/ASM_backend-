<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ColumnConfiguration;

class ColumnConfigurationController extends Controller
{
    /**
     * Listar únicamente las columnas permitidas para la tabla 'prospectos'
     * combinadas con las configuraciones existentes.
     *
     * Se retornan solo estas columnas en el siguiente orden:
     * fecha, nombre_completo, telefono, correo_electronico,
     * empresa_donde_labora_actualmente, puesto, notas_generales,
     * observaciones, interes, status, nota1, nota2, nota3, cierre.
     */
    public function index()
    {
        // Definir las columnas permitidas (orden y nombre exacto a mostrar)
        $allowedColumns = [
            "fecha",
            "nombre_completo",
            "telefono",
            "correo_electronico",
            "empresa_donde_labora_actualmente",
            "puesto",
            "notas_generales",
            "observaciones",
            "interes",
            "status",
            "nota1",
            "nota2",
            "nota3",
            "cierre",
            "medio_conocimiento_institucion"
        ];

        // Obtener las configuraciones existentes para prospectos (id_tipo = 1)
        $configuredColumns = ColumnConfiguration::where('id_tipo', 1)->get();

        // Combinar cada columna permitida con su configuración, retornando 'Activo' siempre.
        $columns = array_map(function ($column) use ($configuredColumns) {
            $configured = $configuredColumns->firstWhere('column_name', $column);
            return [
                'id'                => $configured->id ?? null,
                'column_name'       => $column,
                'excel_column_name' => $configured->excel_column_name ?? '',
                'column_number'     => $configured->column_number ?? '',
                'is_configured'     => $configured ? true : false,
                'status'            => 'Activo',  // Siempre asumido como Activo
            ];
        }, $allowedColumns);

        return response()->json([
            'message' => 'Columnas obtenidas exitosamente',
            'data'    => $columns,
        ]);
    }

    /**
     * Crear o actualizar una configuración de columna para la tabla 'prospectos'.
     *
     * Se espera que el request incluya:
     *  - columnName (nombre de la columna)
     *  - excelColumnName (nombre en Excel)
     *  - columnNumber (número de columna)
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'columnName'      => 'required|string|max:100',
            'excelColumnName' => 'required|string|max:100',
            'columnNumber'    => 'required|integer|min:0',
        ]);

        // Buscar si ya existe una configuración para esta columna (id_tipo = 1 para prospectos)
        $columnConfig = ColumnConfiguration::where('column_name', $validatedData['columnName'])
            ->where('id_tipo', 1)
            ->first();

        if ($columnConfig) {
            // Actualizar la configuración existente
            $columnConfig->excel_column_name = $validatedData['excelColumnName'];
            $columnConfig->column_number = $validatedData['columnNumber'];
            $columnConfig->save();
        } else {
            // Crear la nueva configuración
            ColumnConfiguration::create([
                'id_tipo'           => 1, // Prospectos
                'column_name'       => $validatedData['columnName'],
                'excel_column_name' => $validatedData['excelColumnName'],
                'column_number'     => $validatedData['columnNumber'],
            ]);
        }

        return response()->json([
            'message' => 'Configuración de columna guardada exitosamente',
        ]);
    }


    /**
     * Actualizar de forma específica la configuración de una columna para 'prospectos'.
     *
     * @param Request $request
     * @param int $id  ID de la configuración de columna a actualizar
     */
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'excelColumnName' => 'required|string|max:100',
            'columnNumber'    => 'required|integer|min:0',
        ]);

        $columnConfig = ColumnConfiguration::where('id', $id)
            ->where('id_tipo', 1)
            ->first();

        if (!$columnConfig) {
            return response()->json([
                'message' => 'Configuración no encontrada'
            ], 404);
        }

        $columnConfig->excel_column_name = $validatedData['excelColumnName'];
        $columnConfig->column_number = $validatedData['columnNumber'];
        $columnConfig->save();

        return response()->json([
            'message' => 'Configuración actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar la configuración de una columna para la tabla 'prospectos'.
     *
     * @param Request $request
     * @param int $id  ID de la configuración de columna a eliminar
     */
    public function destroy(Request $request, $id)
    {
        $columnConfig = ColumnConfiguration::where('id', $id)
            ->where('id_tipo', 1)
            ->first();

        if (!$columnConfig) {
            return response()->json([
                'message' => 'Configuración no encontrada'
            ], 404);
        }

        $columnConfig->delete();

        return response()->json([
            'message' => 'Configuración eliminada exitosamente',
        ]);
    }
}
