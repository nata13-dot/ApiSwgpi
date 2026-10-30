# ProtectedDownloadService

Fuente: [app/Services/ProtectedDownloadService.php](../../../app/Services/ProtectedDownloadService.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Services;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class ProtectedDownloadService
{

    /** @return array{string,string} */

}
```

## download

Visibilidad: `public`. Líneas originales 14–36.

```php
    public function download(DocumentVersion $version, ?string $downloadName = null, bool $inline = false): Response|BinaryFileResponse
    {
        [$disk, $path] = $this->resolve($version);
        $name = $downloadName ?: $version->original_name ?: $version->nombre_original
            ?: $version->file_name ?: $version->nombre_archivo;
        $name = basename(str_replace('\\', '/', (string) $name));
        $disposition = HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name ?: 'archivo');
        $headers = [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if (config('uploads.x_accel_downloads', false) && $disk === config('uploads.private_disk')) {
            $prefix = rtrim((string) config('uploads.x_accel_prefix', '/_swgpi_private'), '/');
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

            return response('', 200, $headers + ['X-Accel-Redirect' => $prefix.'/'.$encodedPath]);
        }

        return Storage::disk($disk)->download($path, $name, $headers);
    }
```

## resolve

Visibilidad: `public`. Líneas originales 39–60.

```php
    public function resolve(DocumentVersion $version): array
    {
        $path = $this->safeRelativePath((string) ($version->file_path ?: $version->ruta_archivo));
        $recordedDisk = (string) ($version->disk ?: $version->disco ?: '');
        $disk = $recordedDisk !== '' ? $recordedDisk : (string) config('uploads.legacy_disk', 'legacy_public');

        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            throw new RuntimeException('El disco registrado para el archivo no está configurado.');
        }
        if (Storage::disk($disk)->exists($path)) {
            return [$disk, $path];
        }

        $legacy = (string) config('uploads.legacy_disk', 'legacy_public');
        if (config('uploads.legacy_public_fallback', true)
            && $disk !== $legacy
            && Storage::disk($legacy)->exists($path)) {
            return [$legacy, $path];
        }

        throw new RuntimeException('El archivo no existe en el almacenamiento configurado.');
    }
```

## safeRelativePath

Visibilidad: `private`. Líneas originales 62–75.

```php
    private function safeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('La ruta registrada no es válida.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('La ruta registrada no es válida.');
            }
        }

        return $path;
    }
```
