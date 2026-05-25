<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $body,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->mailSubject)
            ->view('emails.user-credentials', [
                'body' => $this->body,
            ])
            ->text('emails.user-credentials-text', [
                'body' => $this->body,
            ]);
    }
}
