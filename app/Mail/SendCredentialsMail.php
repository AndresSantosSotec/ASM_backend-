<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Prospecto;

class SendCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Prospecto $student;
    public array $credentials;

    public function __construct(Prospecto $student, array $credentials)
    {
        $this->student = $student;
        $this->credentials = $credentials;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tus credenciales de acceso'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credentials',
            with: [
                'student' => $this->student,
                'credentials' => $this->credentials,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
