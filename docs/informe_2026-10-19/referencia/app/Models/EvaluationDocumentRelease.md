# EvaluationDocumentRelease

Fuente: [app/Models/EvaluationDocumentRelease.php](../../../../../app/Models/EvaluationDocumentRelease.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationDocumentRelease extends Model
{
    protected $table = 'liberaciones_documentos_evaluacion';

    protected $fillable = [
        'documento_repositorio_id',
        'alumno_id',
        'liberado',
        'revisado_por',
        'revisado_en',
    ];

    protected $casts = [
        'liberado' => 'boolean',
        'revisado_en' => 'datetime',
    ];



}
```

## document

Visibilidad: `public`. Líneas originales 25–28.

```php
    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_repositorio_id');
    }
```

## student

Visibilidad: `public`. Líneas originales 30–33.

```php
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alumno_id', 'id');
    }
```

## reviewer

Visibilidad: `public`. Líneas originales 35–38.

```php
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por', 'id');
    }
```
