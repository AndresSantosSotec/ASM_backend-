<?php
// app/Services/EmailService.php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericHtmlMail;
use Illuminate\Support\Str;

class EmailService
{
    /**
     * Envío con archivos subidos (multipart/form-data).
     * @param UploadedFile[] $uploadedFiles
     */
    public function sendWithUploadedFiles(string $to, string $subject, string $html, array $uploadedFiles = []): void
    {
        $attachments = [];

        // guardamos temporalmente para adjuntar por path
        foreach ($uploadedFiles as $file) {
            /** @var UploadedFile $file */
            $tmpPath = $file->store('tmp_email'); // storage/app/tmp_email/...
            $attachments[] = [
                'path' => storage_path('app/'.$tmpPath),
                'as'   => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
            ];
        }

        $this->send($to, $subject, $html, $attachments);

        // limpiar archivos temporales
        foreach ($attachments as $a) {
            @unlink($a['path']);
        }
    }

    /**
     * Envío con adjuntos base64 (JSON).
     * attachments[]: ['filename'=>..., 'mime'=>..., 'content_base64'=>...]
     */
    public function sendWithBase64(string $to, string $subject, string $html, array $attachments = []): void
    {
        $tmpFiles = [];

        foreach ($attachments as $att) {
            $filename = $att['filename'] ?? (Str::uuid().'.bin');
            $mime     = $att['mime'] ?? 'application/octet-stream';
            $contentB64 = $att['content_base64'] ?? '';
            $raw = base64_decode($contentB64, true);

            if ($raw === false) continue;

            $tmpPath = storage_path('app/tmp_email/'.Str::uuid().'-'.$filename);
            @mkdir(dirname($tmpPath), 0775, true);
            file_put_contents($tmpPath, $raw);

            $tmpFiles[] = [
                'path' => $tmpPath,
                'as'   => $filename,
                'mime' => $mime,
            ];
        }

        $this->send($to, $subject, $html, $tmpFiles);

        foreach ($tmpFiles as $a) {
            @unlink($a['path']);
        }
    }

    /**
     * Método común de envío (core).
     * $attachments: [['path'=>..., 'as'=>..., 'mime'=>...], ...]
     */
    public function send(string $to, string $subject, string $html, array $attachments = []): void
    {
        Mail::to($to)->send(new GenericHtmlMail($subject, $html, $attachments));
        Log::info('[EmailService] Email enviado', ['to'=>$to, 'subject'=>$subject, 'attachments'=>count($attachments)]);
    }

    // OPCIONAL: encolar a partir de un Request (puedes crear un Job).
    public function queueFromRequest($request): void
    {
        // Aquí puedes mapear el request y despachar un Job que llame a sendWithUploadedFiles o sendWithBase64
        // Buscando mantener breve, omito el Job por ahora.
    }
}
