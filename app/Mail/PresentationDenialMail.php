<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso al alumno de que su presentación (o la de su equipo) se negó o se volvió a permitir. */
class PresentationDenialMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $project,
        public bool $denied,
        public ?string $reason = null,
        public bool $team = false,
    ) {
    }

    public function build(): self
    {
        return $this
            ->tag('presentacion_negada')
            ->subject($this->denied ? 'Tu presentación en SGPI fue negada' : 'Tu presentación en SGPI vuelve a estar permitida')
            ->view('emails.presentation-denial')
            ->text('emails.presentation-denial-text');
    }
}
