<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prospecto;
use App\Models\PasswordResetLog;
use App\Mail\TemporaryPasswordMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryController extends Controller
{
    /**
     * Recuperación de contraseña mediante email.
     *
     * Genera contraseña temporal de 8 caracteres y la envía por correo.
     * Determina el email destino según el rol del usuario:
     * - Estudiante/Prospecto → correo_electronico del prospecto
     * - Otros roles → email del usuario
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recover(Request $request)
    {
        try {
            // Validar el email
            $validated = $request->validate([
                'email' => 'required|email|max:100',
            ]);

            $emailInput = $validated['email'];
            $ipAddress = $request->ip();
            $userAgent = $request->header('User-Agent');

            Log::info('🔐 [PASSWORD RECOVERY] Solicitud recibida', [
                'email' => $emailInput,
                'ip' => $ipAddress,
                'user_agent' => substr($userAgent ?? '', 0, 100),
            ]);

            // Buscar usuario por email
            $user = User::where('email', $emailInput)->first();

            // IMPORTANTE: No revelar si el email existe o no (prevenir enumeración)
            if (!$user) {
                Log::warning('⚠️ [PASSWORD RECOVERY] Email no encontrado', [
                    'email' => $emailInput,
                    'ip' => $ipAddress,
                ]);

                // Respuesta genérica (misma que cuando tiene éxito)
                return response()->json([
                    'success' => true,
                    'message' => 'Si el correo electrónico está registrado, recibirás un email con tu nueva contraseña temporal.',
                ], 200);
            }

            // Iniciar transacción
            DB::beginTransaction();

            try {
                // Determinar email destino según el rol
                $emailDestino = $this->determineDestinationEmail($user);

                if (!$emailDestino) {
                    Log::error('❌ [PASSWORD RECOVERY] No se pudo determinar email destino', [
                        'user_id' => $user->id,
                        'carnet' => $user->carnet,
                    ]);

                    $this->logRecoveryAttempt($user->id, 'unknown@error.com', $ipAddress, $userAgent, 'failed', 'Email destino no disponible');

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Si el correo electrónico está registrado, recibirás un email con tu nueva contraseña temporal.',
                    ], 200);
                }

                // Generar contraseña temporal segura (8 caracteres)
                $temporaryPassword = $this->generateSecurePassword();

                Log::info('🔑 [PASSWORD RECOVERY] Contraseña temporal generada', [
                    'user_id' => $user->id,
                    'email_destino' => $emailDestino,
                    'password_length' => strlen($temporaryPassword),
                ]);

                // Actualizar contraseña del usuario
                $user->password = Hash::make($temporaryPassword);
                $user->save();

                Log::info('💾 [PASSWORD RECOVERY] Contraseña actualizada en BD', [
                    'user_id' => $user->id,
                ]);

                // Obtener nombre del usuario
                $userName = $this->getUserName($user);

                // Enviar email con contraseña temporal
                Mail::to($emailDestino)->send(new TemporaryPasswordMail(
                    $userName,
                    $temporaryPassword,
                    $user->carnet
                ));

                Log::info('📧 [PASSWORD RECOVERY] Email enviado exitosamente', [
                    'user_id' => $user->id,
                    'email_destino' => $emailDestino,
                    'user_name' => $userName,
                ]);

                // Registrar intento exitoso
                $this->logRecoveryAttempt($user->id, $emailDestino, $ipAddress, $userAgent, 'success', 'Contraseña temporal enviada exitosamente');

                DB::commit();

                Log::info('✅ [PASSWORD RECOVERY] Proceso completado exitosamente', [
                    'user_id' => $user->id,
                    'email_destino' => $emailDestino,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Si el correo electrónico está registrado, recibirás un email con tu nueva contraseña temporal.',
                ], 200);

            } catch (\Throwable $th) {
                DB::rollBack();

                Log::error('❌ [PASSWORD RECOVERY] Error en transacción', [
                    'user_id' => $user->id ?? null,
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString(),
                ]);

                // Registrar intento fallido
                if (isset($user->id)) {
                    $this->logRecoveryAttempt($user->id, $emailDestino ?? $emailInput, $ipAddress, $userAgent, 'failed', $th->getMessage());
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error al procesar la solicitud. Por favor, inténtalo de nuevo.',
                ], 500);
            }

        } catch (ValidationException $e) {
            Log::warning('⚠️ [PASSWORD RECOVERY] Validación fallida', [
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico proporcionado no es válido.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $th) {
            Log::error('❌ [PASSWORD RECOVERY] Error crítico', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado. Por favor, contacta al administrador.',
            ], 500);
        }
    }

    /**
     * Determina el email destino según el rol del usuario.
     *
     * @param User $user
     * @return string|null
     */
    private function determineDestinationEmail(User $user): ?string
    {
        // Cargar la relación userRole con el rol
        $user->load('userRole.role');

        $roleName = $user->userRole?->role?->nombre_rol ?? null;

        Log::info('🔍 [PASSWORD RECOVERY] Determinando email destino', [
            'user_id' => $user->id,
            'role' => $roleName,
            'carnet' => $user->carnet,
        ]);

        // Si el usuario tiene rol de Estudiante o Prospecto, usar correo del prospecto
        if (in_array($roleName, ['Estudiante', 'Prospecto'])) {
            if ($user->carnet) {
                $prospecto = Prospecto::where('carnet', $user->carnet)->first();

                if ($prospecto && $prospecto->correo_electronico) {
                    Log::info('📮 [PASSWORD RECOVERY] Usando correo del prospecto', [
                        'carnet' => $user->carnet,
                        'email' => $prospecto->correo_electronico,
                    ]);

                    return $prospecto->correo_electronico;
                } else {
                    Log::warning('⚠️ [PASSWORD RECOVERY] Prospecto no encontrado o sin email', [
                        'carnet' => $user->carnet,
                    ]);
                }
            }
        }

        // Para otros roles o si no hay prospecto, usar el email del usuario
        Log::info('📮 [PASSWORD RECOVERY] Usando correo del usuario', [
            'email' => $user->email,
        ]);

        return $user->email;
    }

    /**
     * Genera una contraseña segura de 8 caracteres.
     * Incluye: mayúsculas, minúsculas, números y al menos 1 carácter especial.
     *
     * @return string
     */
    private function generateSecurePassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%&*';

        // Asegurar al menos 1 de cada tipo
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)]; // 1 mayúscula
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)]; // 1 minúscula
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];     // 1 número
        $password .= $special[random_int(0, strlen($special) - 1)];     // 1 especial

        // Completar hasta 8 caracteres con caracteres aleatorios
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < 8; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Mezclar los caracteres
        $password = str_shuffle($password);

        return $password;
    }

    /**
     * Obtiene el nombre del usuario para personalizar el email.
     *
     * @param User $user
     * @return string
     */
    private function getUserName(User $user): string
    {
        // Intentar obtener nombre del prospecto si existe
        if ($user->carnet) {
            $prospecto = Prospecto::where('carnet', $user->carnet)->first();

            if ($prospecto && $prospecto->nombre_completo) {
                return $prospecto->nombre_completo;
            }
        }

        // Si no, usar el nombre del usuario o su email
        return $user->name ?? explode('@', $user->email)[0];
    }

    /**
     * Registra el intento de recuperación de contraseña.
     *
     * @param int $userId
     * @param string $emailDestino
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string $status
     * @param string|null $notes
     * @return void
     */
    private function logRecoveryAttempt(
        int $userId,
        string $emailDestino,
        ?string $ipAddress,
        ?string $userAgent,
        string $status,
        ?string $notes = null
    ): void {
        try {
            PasswordResetLog::create([
                'user_id' => $userId,
                'email_destino' => $emailDestino,
                'ip_address' => $ipAddress,
                'user_agent' => substr($userAgent ?? '', 0, 255),
                'status' => $status,
                'reset_method' => 'temporary_password',
                'notes' => $notes,
            ]);

            Log::info('📝 [PASSWORD RECOVERY] Log guardado', [
                'user_id' => $userId,
                'status' => $status,
            ]);

        } catch (\Throwable $th) {
            Log::error('❌ [PASSWORD RECOVERY] Error al guardar log', [
                'user_id' => $userId,
                'error' => $th->getMessage(),
            ]);
        }
    }
}
