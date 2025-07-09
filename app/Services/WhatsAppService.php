<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $sid;
    protected string $token;
    protected string $from;

    public function __construct()
    {
        $this->sid = config('services.whatsapp.sid');
        $this->token = config('services.whatsapp.token');
        $this->from = config('services.whatsapp.from');
    }

    public function sendMessage(string $to, string $message): void
    {
        if (!$this->sid || !$this->token || !$this->from) {
            throw new \RuntimeException('WhatsApp credentials not configured');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";

        Http::withBasicAuth($this->sid, $this->token)
            ->asForm()
            ->post($url, [
                'From' => "whatsapp:{$this->from}",
                'To'   => "whatsapp:{$to}",
                'Body' => $message,
            ]);
    }
}
