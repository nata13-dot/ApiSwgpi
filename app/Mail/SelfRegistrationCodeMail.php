<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SelfRegistrationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $career,
        public string $token,
    ) {
    }

    public function build(): self
    {
        $data = ['name' => $this->name, 'career' => $this->career, 'token' => $this->token];

        return $this
            ->subject('Código para activar tu cuenta SGPI')
            ->view('emails.self-registration-code', $data)
            ->text('emails.self-registration-code-text', $data);
    }
}
