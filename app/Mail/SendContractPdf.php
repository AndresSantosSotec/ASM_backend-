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
    public $fecha;
    public $programa;
    public $inscripcion;
    public $mensualidad;
    public $convenio_id;

    public function __construct(
        $student,
        $pdfData,
        $fecha,
        $programa,
        $inscripcion,
        $mensualidad,
        $convenio_id
    ) {
        $this->student     = $student;
        $this->pdfData     = $pdfData;
        $this->fecha       = $fecha;
        $this->programa    = $programa;
        $this->inscripcion = $inscripcion;
        $this->mensualidad = $mensualidad;
        $this->convenio_id = $convenio_id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu Contrato de Confidencialidad, {$this->student->nombre_completo}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confidencialidad',
            with: [
                'student'      => $this->student,
                'fecha'        => $this->fecha,
                'programa'     => $this->programa,
                'inscripcion'  => $this->inscripcion,
                'mensualidad'  => $this->mensualidad,
                'convenio_id'  => $this->convenio_id,
                'signature'    => $this->signature ?? null,
            ]
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn() => $this->pdfData,
                'Contrato_Confidencialidad_' . $this->student->nombre_completo . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
