<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetTokenMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
    ) {
    }

    public function build(): self
    {
        $name = trim("{$this->user->nombres} {$this->user->apa}") ?: $this->user->id;

        return $this
            ->subject('Token de recuperacion de contrasena')
            ->view('emails.password-reset-token', [
                'name' => $name,
                'token' => $this->token,
            ])
            ->text('emails.password-reset-token-text', [
                'name' => $name,
                'token' => $this->token,
            ]);
    }
}
