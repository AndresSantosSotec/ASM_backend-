<?php
// app/Mail/GenericHtmlMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericHtmlMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public array $attachmentsInfo = [] // [['path','as','mime'], ...]
    ) {}

    public function build()
    {
        $mail = $this->subject($this->subjectLine)
            ->html($this->htmlBody);

        foreach ($this->attachmentsInfo as $a) {
            $mail->attach($a['path'], [
                'as'   => $a['as'] ?? null,
                'mime' => $a['mime'] ?? null,
            ]);
        }
        return $mail;
    }
}
