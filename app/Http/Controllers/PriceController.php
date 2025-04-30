<?php
namespace App\Http\Controllers;

use App\Models\PrecioPrograma;
use App\Models\PrecioConvenioPrograma;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    public function porPrograma(Request $request, $programaId)
    {
        $mesesInput = (int) $request->query('meses', 0);

        $query = PrecioPrograma::where('programa_id', $programaId);

        $price = $mesesInput > 0
            ? $query->orderByRaw('ABS(meses - ?) ASC', [$mesesInput])->firstOrFail()
            : $query->firstOrFail();

        return response()->json([
            'inscripcion'   => (float) $price->inscripcion,
            'cuota_mensual' => (float) $price->cuota_mensual,
            'meses'         => $price->meses,
        ]);
    }

    public function porConvenio(Request $request, $convenioId, $programaId)
    {
        $mesesInput = (int) $request->query('meses', 0);

        $query = PrecioConvenioPrograma::where('convenio_id', $convenioId)
                                       ->where('programa_id', $programaId);

        $price = $mesesInput > 0
            ? $query->orderByRaw('ABS(meses - ?) ASC', [$mesesInput])->firstOrFail()
            : $query->firstOrFail();

        return response()->json([
            'inscripcion'   => (float) $price->inscripcion,
            'cuota_mensual' => (float) $price->cuota_mensual,
            'meses'         => $price->meses,
        ]);
    }
}
