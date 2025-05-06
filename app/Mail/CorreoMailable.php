<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CorreoMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Crea una nueva instancia del mensaje.
     *
     * @param array $data  Se espera un arreglo con keys 'asunto', 'mensaje' y opcionalmente 'bcc'
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Obtiene el sobre del mensaje.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['asunto'] ?? 'Correo Mailable',
            bcc: $this->data['bcc'] ?? []  // Se envían los BCC si existen
        );
    }

    /**
     * Obtiene la definición del contenido del mensaje.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.plantilla',
            with: ['data' => $this->data]
        );
    }

    /**
     * Obtiene los adjuntos para el mensaje.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}