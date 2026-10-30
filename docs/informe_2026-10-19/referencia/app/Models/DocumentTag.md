# DocumentTag

Fuente: [app/Models/DocumentTag.php](../../../../../app/Models/DocumentTag.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentTag extends Model
{
    use BelongsToCareer, HasFactory;

    protected $table = 'etiquetas';

    public $timestamps = false;

    protected $fillable = ['carrera_id', 'nombre', 'color', 'descripcion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // RELACIONES

    // SCOPES
}
```

## repositoryDocuments

Visibilidad: `public`. Líneas originales 25–28.

```php
    public function repositoryDocuments(): BelongsToMany
    {
        return $this->belongsToMany(RepositoryDocument::class, 'documento_etiquetas', 'etiqueta_id', 'documento_id');
    }
```

## scopeActivas

Visibilidad: `public`. Líneas originales 31–34.

```php
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
```
