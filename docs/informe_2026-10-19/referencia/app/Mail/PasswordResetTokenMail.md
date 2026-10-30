# PasswordResetTokenMail

Fuente: [app/Mail/PasswordResetTokenMail.php](../../../../../app/Mail/PasswordResetTokenMail.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetTokenMail extends Mailable
{
    use Queueable, SerializesModels;


}
```

## __construct

Visibilidad: `public`. Líneas originales 14–18.

```php
    public function __construct(
        public User $user,
        public string $token,
    ) {
    }
```

## build

Visibilidad: `public`. Líneas originales 20–34.

```php
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
```
