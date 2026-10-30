# CareerContext

Fuente: [app/Support/CareerContext.php](../../../../../app/Support/CareerContext.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Support;

use App\Models\Career;
use App\Models\User;

class CareerContext
{
    private ?Career $career = null;
    private ?int $profileId = null;
    private ?User $user = null;






}
```

## set

Visibilidad: `public`. Líneas originales 14–19.

```php
    public function set(User $user, Career $career, int $profileId): void
    {
        $this->user = $user;
        $this->career = $career;
        $this->profileId = $profileId;
    }
```

## career

Visibilidad: `public`. Líneas originales 21–24.

```php
    public function career(): ?Career
    {
        return $this->career;
    }
```

## careerId

Visibilidad: `public`. Líneas originales 26–29.

```php
    public function careerId(): ?int
    {
        return $this->career?->getKey();
    }
```

## profileId

Visibilidad: `public`. Líneas originales 31–34.

```php
    public function profileId(): ?int
    {
        return $this->profileId;
    }
```

## user

Visibilidad: `public`. Líneas originales 36–39.

```php
    public function user(): ?User
    {
        return $this->user;
    }
```

## isGeneralAdmin

Visibilidad: `public`. Líneas originales 41–44.

```php
    public function isGeneralAdmin(): bool
    {
        return $this->user?->globalProfileId() === 4;
    }
```
