<?php

namespace App\Http\Requests;

use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use App\Support\Boletas;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubirReciboRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            'cuota_id' => 'required|integer|exists:cuotas_programa_estudiante,id',
            'numero_boleta' => 'required|string|max:100',
            'banco' => 'required|string|max:100',
            'monto' => 'required|numeric|min:0',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ];
    }

    public function messages()
    {
        return [
            'cuota_id.required' => 'El ID de la cuota es obligatorio.',
            'cuota_id.exists' => 'La cuota seleccionada no existe.',
            'numero_boleta.required' => 'El número de boleta es obligatorio.',
            'numero_boleta.max' => 'El número de boleta no puede exceder 100 caracteres.',
            'banco.required' => 'El banco es obligatorio.',
            'banco.max' => 'El nombre del banco no puede exceder 100 caracteres.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un valor numérico.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'comprobante.required' => 'El comprobante es obligatorio.',
            'comprobante.file' => 'El comprobante debe ser un archivo válido.',
            'comprobante.mimes' => 'El comprobante debe ser un archivo PDF, JPG, JPEG o PNG.',
            'comprobante.max' => 'El comprobante no puede exceder 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'field_errors' => $validator->errors()
            ], 422)
        );
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $user = Auth::user();
            
            // Check if cuota belongs to the authenticated user and is pending
            $cuota = CuotaProgramaEstudiante::whereHas('estudiantePrograma.prospecto', function ($query) use ($user) {
                $query->where('carnet', $user->carnet);
            })
            ->where('id', $this->cuota_id)
            ->first();

            if (!$cuota) {
                $validator->errors()->add('cuota_id', 'La cuota no pertenece al estudiante autenticado.');
                return;
            }

            if ($cuota->estado !== 'pendiente') {
                $validator->errors()->add('cuota_id', 'La cuota no está en estado pendiente.');
                return;
            }

            // Check for early payment (if policy is enabled)
            $allowEarlyPayment = config('payment.allow_early_payment', true);
            if (!$allowEarlyPayment && Carbon::parse($cuota->fecha_vencimiento)->isFuture()) {
                $validator->errors()->add('cuota_id', 'Esta cuota aún no está habilitada para pago.');
                return;
            }

            // Normalize boleta and bank data
            $boletaNorm = Boletas::normalize($this->numero_boleta);
            $bancoNorm = Boletas::normalizeBank($this->banco);

            // Check for exact duplicate (same student, bank, boleta)
            $exactDuplicate = KardexPago::where('estudiante_programa_id', $cuota->estudiante_programa_id)
                ->where('banco_norm', $bancoNorm)
                ->where('numero_boleta_norm', $boletaNorm)
                ->exists();

            if ($exactDuplicate) {
                $validator->errors()->add('numero_boleta', 'Esta boleta ya ha sido registrada para este banco y estudiante.');
            }

            // Check for soft duplicate (same boleta in different banks or programs)
            $softDuplicate = KardexPago::whereHas('estudiantePrograma.prospecto', function ($query) use ($user) {
                $query->where('carnet', $user->carnet);
            })
            ->where('numero_boleta_norm', $boletaNorm)
            ->where(function($query) use ($bancoNorm, $cuota) {
                $query->where('banco_norm', '!=', $bancoNorm)
                      ->orWhere('estudiante_programa_id', '!=', $cuota->estudiante_programa_id);
            })
            ->exists();

            if ($softDuplicate) {
                $validator->errors()->add('numero_boleta', 'Se detectó una posible duplicidad: esta boleta podría estar siendo utilizada en otro programa o banco.');
            }

            // Check for file hash duplicate (if file is provided)
            if ($this->hasFile('comprobante')) {
                $fileContent = file_get_contents($this->file('comprobante')->getRealPath());
                $fileHash = Boletas::calculateFileHash($fileContent);
                
                $hashDuplicate = KardexPago::where('estudiante_programa_id', $cuota->estudiante_programa_id)
                    ->where('file_sha256', $fileHash)
                    ->exists();

                if ($hashDuplicate) {
                    $validator->errors()->add('comprobante', 'Este archivo ya ha sido utilizado anteriormente.');
                }

                // Store hash for later use in controller
                $this->merge(['file_hash' => $fileHash]);
            }

            // Store normalized values for later use
            $this->merge([
                'numero_boleta_norm' => $boletaNorm,
                'banco_norm' => $bancoNorm,
                'cuota_model' => $cuota
            ]);
        });
    }
}