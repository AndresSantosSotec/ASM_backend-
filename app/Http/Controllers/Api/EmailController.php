<?php
// app/Http/Controllers/Api/EmailController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\EmailService;

class EmailController extends Controller
{
    public function __construct(private EmailService $emailService) {}

    /**
     * Acepta:
     * - multipart/form-data: to, subject, html, attachments[] (File)
     * - application/json: { to, subject, html, attachments?: [{filename,mime,content_base64}] }
     */
    public function send(Request $request)
    {
        // ====== LOG INICIAL (para confirmar que LLEGA) ======
        $hasFiles = $request->hasFile('attachments');
        $contentType = $request->header('Content-Type', '');
        Log::info('[EmailController] /emails/send hit', [
            'content_type' => $contentType,
            'all_except_files' => $request->except('attachments'),
            'has_files' => $hasFiles,
            'files_count' => $hasFiles ? count($request->file('attachments')) : 0,
        ]);

        // Detección robusta de multipart: por files o por header
        $isMultipart = $hasFiles || str_contains($contentType, 'multipart/form-data');

        // ====== VALIDACIÓN ======
        $rules = [
            'to'      => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'html'    => ['required', 'string'],
        ];

        if ($isMultipart) {
            // Los archivos llegan como attachments[] (Laravel los agrupa en 'attachments')
            $rules['attachments.*'] = ['file', 'max:5120']; // 5MB c/u
        } else {
            $rules['attachments'] = ['nullable', 'array'];
            $rules['attachments.*.filename'] = ['required_with:attachments', 'string', 'max:255'];
            $rules['attachments.*.mime']     = ['required_with:attachments', 'string', 'max:100'];
            $rules['attachments.*.content_base64'] = ['required_with:attachments', 'string'];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            Log::warning('[EmailController] validation error', ['errors' => $v->errors()]);
            return response()->json(['message' => 'Validation error', 'errors' => $v->errors()], 422);
        }

        try {
            // Castear a string (evitar Stringable)
            $to      = (string) $request->input('to');
            $subject = (string) $request->input('subject');
            $html    = (string) $request->input('html');

            if ($isMultipart) {
                // ====== MULTIPART ======
                // Importante: en el front usa form.append('attachments[]', file)
                $files = $request->file('attachments', []);
                Log::info('[EmailController] multipart payload', [
                    'to' => $to, 'subject' => $subject, 'files_count' => count($files),
                ]);

                $this->emailService->sendWithUploadedFiles(
                    to: $to,
                    subject: $subject,
                    html: $html,
                    uploadedFiles: $files,
                );
            } else {
                // ====== JSON base64 ======
                $attachments = $request->input('attachments', []);
                Log::info('[EmailController] json payload', [
                    'to' => $to, 'subject' => $subject, 'attachments_count' => is_array($attachments) ? count($attachments) : 0,
                ]);

                $this->emailService->sendWithBase64(
                    to: $to,
                    subject: $subject,
                    html: $html,
                    attachments: is_array($attachments) ? $attachments : [],
                );
            }

            // Respuesta con "eco" para mostrar en un toast
            return response()->json([
                'message' => 'Email enviado con éxito',
                'echo' => [
                    'to' => $to,
                    'subject' => $subject,
                    'is_multipart' => $isMultipart,
                    'has_files' => $isMultipart ? $hasFiles : false,
                    'files_count' => $isMultipart && $hasFiles ? count($request->file('attachments')) : 0,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[EmailController] send error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'No se pudo enviar el correo'], 500);
        }
    }

    // OPCIONAL: envío en cola
    public function sendQueued(Request $request)
    {
        try {
            $this->emailService->queueFromRequest($request);
            return response()->json(['message' => 'Email encolado']);
        } catch (\Throwable $e) {
            Log::error('[EmailController] sendQueued error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo encolar el correo'], 500);
        }
    }
}
