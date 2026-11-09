<?php

namespace App\Models\Concerns;

use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo archivado deja de verse en todo el sistema hasta que se restaura desde el archivo histórico.
 * La página del archivo lo consulta con withoutGlobalScope('archived').
 */
trait Archivable
{
    protected static function bootArchivable(): void
    {
        static::addGlobalScope('archived', function (Builder $query): void {
            $table = $query->getModel()->getTable();
            // Sin la migración aplicada el sistema sigue funcionando como antes.
            if (SchemaCache::hasColumn($table, 'archivado_en')) {
                $query->whereNull("{$table}.archivado_en");
            }
        });
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('archived')->whereNotNull($this->getTable() . '.archivado_en');
    }
}
