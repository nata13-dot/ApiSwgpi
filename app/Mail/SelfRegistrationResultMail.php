<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SelfRegistrationResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $career,
        public string $controlNumber,
        public bool $approved,
        public ?string $reason = null,
    ) {
    }

    public function build(): self
    {
        return $this
            ->tag('resultado_registro')
            ->subject($this->approved ? 'Tu cuenta SGPI fue aprobada' : 'Tu solicitud de cuenta SGPI fue rechazada')
            ->view('emails.self-registration-result')
            ->text('emails.self-registration-result-text');
    }
}
