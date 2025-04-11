<?php

namespace App\Imports;

use App\Models\Prospecto;
use App\Models\ColumnConfiguration;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ProspectosImport implements ToCollection, WithHeadingRow
{
    protected $mapping = [];
    protected $userId;

    /**
     * @param int $userId  ID del usuario autenticado o valor por defecto (por ejemplo, 0)
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;

        // Obtener el mapping de ColumnConfiguration (id_tipo = 1 para prospectos)
        $mappings = ColumnConfiguration::where('id_tipo', 1)
            ->pluck('column_name', 'excel_column_name')
            ->toArray();

        // Normalizar las claves (convertir a minúsculas y quitar espacios en blanco)
        foreach ($mappings as $excelCol => $dbColumn) {
            $this->mapping[strtolower(trim($excelCol))] = $dbColumn;
        }
    }

    /**
     * Procesa la colección del Excel.
     * Se espera que WithHeadingRow transforme cada fila en un arreglo asociativo.
     *
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            $rowData   = $row->toArray();
            $mappedData = [];

            // Mapear datos usando el mapping configurado
            foreach ($this->mapping as $excelCol => $dbColumn) {
                $mappedData[$dbColumn] = $rowData[$excelCol] ?? null;
            }

            // Procesar el campo "fecha"
            if (isset($mappedData['fecha'])) {
                $parsedDate = $this->parseDate($mappedData['fecha']);
                if (!$parsedDate) {
                    Log::warning("Fecha inválida: ", $rowData);
                    $parsedDate = Carbon::now()->format('Y-m-d');
                }
                $mappedData['fecha'] = $parsedDate;
            } else {
                Log::warning("Fecha faltante en la fila: ", $rowData);
                $mappedData['fecha'] = Carbon::now()->format('Y-m-d');
            }

            // Procesar el campo "cierre" (opcional)
            if (isset($mappedData['cierre']) && !empty($mappedData['cierre'])) {
                $parsedCierre = $this->parseDate($mappedData['cierre']);
                if ($parsedCierre !== false) {
                    $mappedData['cierre'] = $parsedCierre;
                }
            }

            // Asignar valores por defecto a campos críticos para evitar NULL en columnas NOT NULL
            $defaults = [
                'nombre_completo'    => "Sin Nombre",
                'telefono'           => "000000000",
                'correo_electronico' => "no-email@example.com",
                'genero'             => "Sin Género",
            ];
            foreach ($defaults as $field => $default) {
                if (empty($mappedData[$field])) {
                    $mappedData[$field] = $default;
                }
            }

            // Actualización para el campo "interes":
            // Se busca en tb_programas mediante la concatenación (sin espacios) de abreviatura y meses.
            if (isset($mappedData['interes'])) {
                $programInput = trim($mappedData['interes']);
                $program = DB::table('tb_programas')
                    ->select('id')
                    ->whereRaw("REPLACE(CONCAT(abreviatura, meses), ' ', '') = ?", [str_replace(' ', '', $programInput)])
                    ->first();
                if ($program) {
                    $mappedData['interes'] = $program->id;
                } else {
                    Log::warning("Programa de interés no encontrado: $programInput", $rowData);
                    $mappedData['interes'] = null;
                }
            }

            // Asignar el valor para "created_by" usando el id del usuario autenticado
            $mappedData['created_by'] = $this->userId;

            // Insertar el registro
            try {
                Prospecto::create($mappedData);
            } catch (\Exception $e) {
                Log::error("Error al insertar prospecto: " . $e->getMessage(), $mappedData);
            }
        }
    }

    /**
     * Intenta convertir una fecha en formato d/m/Y a Y-m-d.
     * Además, si el valor es numérico (fecha serial de Excel) o en formato Y/m/d, lo convierte.
     * Si falla, retorna false para asignar un valor por defecto.
     *
     * @param mixed $value
     * @return string|false
     */
    private function parseDate($value)
    {
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
            } catch (\Exception $e) {
                return false;
            }
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('Y/m/d', $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return false;
            }
        }
    }
}
