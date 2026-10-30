# UserCredentialsMail

Fuente: [app/Mail/UserCredentialsMail.php](../../../../../app/Mail/UserCredentialsMail.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;


}
```

## __construct

Visibilidad: `public`. Líneas originales 13–17.

```php
    public function __construct(
        public string $mailSubject,
        public string $body,
    ) {
    }
```

## build

Visibilidad: `public`. Líneas originales 19–29.

```php
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
```
