<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class SendContractPdf extends Mailable
{
    use Queueable, SerializesModels;

    public $student;
    public $pdfData;

    /**
     * Recibimos aquí el prospecto y el PDF en memoria.
     */
    public function __construct($student, $pdfData)
    {
        $this->student = $student;
        $this->pdfData  = $pdfData;
    }

    /**
     * Configuramos asunto, remitente, etc.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu Contrato de Confidencialidad, {$this->student->nombre_completo}"
        );
    }

    /**
     * Le decimos qué vista usar y qué datos pasarle.
     */
    // En App\Mail\SendContractPdf
    public function content(): Content
    {
        return new Content(
            view: 'emails.confidencialidad', // <— aquí busca resources/views/emails/contract_ready.blade.php
            with: ['student' => $this->student]
        );
    }


    /**
     * Adjuntamos el PDF recién generado.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                // Closure que devuelve el string binario
                fn() => $this->pdfData,
                // Nombre de archivo dinámico
                'Contrato_Confidencialidad_' . $this->student->nombre_completo . '.pdf'
            )
                ->withMime('application/pdf'),
        ];
    }
}
