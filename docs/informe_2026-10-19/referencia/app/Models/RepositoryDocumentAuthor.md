# RepositoryDocumentAuthor

Fuente: [app/Models/RepositoryDocumentAuthor.php](../../../../../app/Models/RepositoryDocumentAuthor.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryDocumentAuthor extends Model
{
    protected $table = 'documentos_repositorio_autores';

    public $timestamps = false;

    protected $fillable = [
        'documento_repositorio_id',
        'usuario_id',
        'nombre_autor',
    ];


}
```

## document

Visibilidad: `public`. Líneas originales 20–23.

```php
    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_repositorio_id');
    }
```

## user

Visibilidad: `public`. Líneas originales 25–28.

```php
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
```
