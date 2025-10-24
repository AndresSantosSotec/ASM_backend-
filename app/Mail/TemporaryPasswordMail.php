<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $temporaryPassword;
    public $carnet;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userName, string $temporaryPassword, string $carnet = null)
    {
        $this->userName = $userName;
        $this->temporaryPassword = $temporaryPassword;
        $this->carnet = $carnet;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperación de contraseña - Sistema ASMProlink',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.temporary-password',
            with: [
                'userName' => $this->userName,
                'temporaryPassword' => $this->temporaryPassword,
                'carnet' => $this->carnet,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
