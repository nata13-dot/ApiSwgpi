# StoredFileService

Fuente: [app/Services/StoredFileService.php](../../../app/Services/StoredFileService.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class StoredFileService
{
    private const MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/cdfv2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/cdfv2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/cdfv2'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'epub' => ['application/epub+zip', 'application/zip'],
        'rar' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
    ];

    /**
     * Validate an upload and copy it to private temporary storage.
     *
     * @return array{temporary_disk:string,temporary_path:string,original_name:string,file_name:string,extension:string,mime_type:string,size_bytes:int,checksum_sha256:string}
     */

    /**
     * Promote a prepared file to its immutable final path.
     *
     * @return array{disk:string,path:string}
     */









}
```

## prepare

Visibilidad: `public`. Líneas originales 38–96.

```php
    public function prepare(
        UploadedFile $file,
        ?array $allowedExtensions = null,
        ?int $maxSizeMb = null
    ): array {
        if (! $file->isValid() || ! $file->getRealPath()) {
            $this->invalid('El archivo es inválido o no pudo recibirse completamente.');
        }

        $originalName = $this->sanitizeOriginalName($file->getClientOriginalName());
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = collect($allowedExtensions ?? config('uploads.default_extensions', []))
            ->map(fn ($value) => strtolower(ltrim((string) $value, '.')))
            ->unique()
            ->values()
            ->all();

        $this->validateNameAndExtension($originalName, $extension, $allowed);

        $size = (int) $file->getSize();
        $maxBytes = ($maxSizeMb ?? (int) config('uploads.max_size_mb', 50)) * 1024 * 1024;
        if ($size < 1 || $size > $maxBytes) {
            $this->invalid('El archivo está vacío o excede el tamaño máximo permitido.');
        }

        $realPath = $file->getRealPath();
        $mime = strtolower((new \finfo(FILEINFO_MIME_TYPE))->file($realPath) ?: 'application/octet-stream');
        if (! $this->mimeMatches($extension, $mime, $realPath)) {
            $this->invalid("El contenido del archivo no corresponde con la extensión .{$extension}.");
        }

        $checksum = hash_file('sha256', $realPath);
        if (! $checksum) {
            throw new RuntimeException('No se pudo calcular la integridad del archivo.');
        }

        $fileName = Str::lower((string) Str::ulid()).'.'.$extension;
        $temporaryDisk = (string) config('uploads.temporary_disk', 'local');
        $temporaryDirectory = trim((string) config('uploads.temporary_directory', '.temporary/uploads'), '/');
        $temporaryPath = Storage::disk($temporaryDisk)->putFileAs(
            $temporaryDirectory,
            $file,
            Str::lower((string) Str::ulid()).'.upload'
        );
        if (! $temporaryPath) {
            throw new RuntimeException('No se pudo guardar temporalmente el archivo.');
        }

        return [
            'temporary_disk' => $temporaryDisk,
            'temporary_path' => $temporaryPath,
            'original_name' => $originalName,
            'file_name' => $fileName,
            'extension' => $extension,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
        ];
    }
```

## promote

Visibilidad: `public`. Líneas originales 103–135.

```php
    public function promote(array $prepared, string $directory, ?string $disk = null): array
    {
        $disk ??= (string) config('uploads.write_disk', 'legacy_public');
        $directory = $this->safeRelativePath($directory);
        $destination = $directory.'/'.$prepared['file_name'];
        $temporaryDisk = (string) $prepared['temporary_disk'];
        $temporaryPath = (string) $prepared['temporary_path'];

        if (Storage::disk($disk)->exists($destination)) {
            throw new RuntimeException('La ruta generada para el archivo ya existe.');
        }

        if ($disk === $temporaryDisk) {
            if (! Storage::disk($disk)->move($temporaryPath, $destination)) {
                throw new RuntimeException('No se pudo promover el archivo temporal.');
            }
        } else {
            $stream = Storage::disk($temporaryDisk)->readStream($temporaryPath);
            if (! is_resource($stream)) {
                throw new RuntimeException('No se pudo leer el archivo temporal.');
            }
            try {
                if (! Storage::disk($disk)->writeStream($destination, $stream)) {
                    throw new RuntimeException('No se pudo escribir el archivo definitivo.');
                }
            } finally {
                fclose($stream);
            }
            Storage::disk($temporaryDisk)->delete($temporaryPath);
        }

        return ['disk' => $disk, 'path' => $destination];
    }
```

## discardPrepared

Visibilidad: `public`. Líneas originales 137–143.

```php
    public function discardPrepared(?array $prepared): void
    {
        if (! $prepared || empty($prepared['temporary_disk']) || empty($prepared['temporary_path'])) {
            return;
        }
        Storage::disk($prepared['temporary_disk'])->delete($prepared['temporary_path']);
    }
```

## discardStored

Visibilidad: `public`. Líneas originales 145–151.

```php
    public function discardStored(?array $stored): void
    {
        if (! $stored || empty($stored['disk']) || empty($stored['path'])) {
            return;
        }
        Storage::disk($stored['disk'])->delete($stored['path']);
    }
```

## sanitizeOriginalName

Visibilidad: `public`. Líneas originales 153–166.

```php
    public function sanitizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        $name = preg_replace('/[^\pL\pN ._()\[\]-]+/u', '_', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        if ($name === '') {
            $this->invalid('El nombre original del archivo no es válido.');
        }

        return mb_strimwidth($name, 0, 255, '');
    }
```

## validateNameAndExtension

Visibilidad: `private`. Líneas originales 168–183.

```php
    private function validateNameAndExtension(string $name, string $extension, array $allowed): void
    {
        $executable = array_map('strtolower', config('uploads.executable_extensions', []));
        $segments = array_map('strtolower', explode('.', $name));
        array_shift($segments);

        if ($extension === '' || in_array($extension, $executable, true)) {
            $this->invalid('La extensión del archivo no está permitida.');
        }
        if (! in_array($extension, $allowed, true) || ! isset(self::MIME_BY_EXTENSION[$extension])) {
            $this->invalid('Tipo de archivo no permitido. Permitidos: '.strtoupper(implode(', ', $allowed)).'.');
        }
        if (collect(array_slice($segments, 0, -1))->contains(fn ($segment) => in_array($segment, $executable, true))) {
            $this->invalid('Se rechazó un nombre con doble extensión peligrosa.');
        }
    }
```

## mimeMatches

Visibilidad: `private`. Líneas originales 185–198.

```php
    private function mimeMatches(string $extension, string $mime, string $path): bool
    {
        if (! in_array($mime, self::MIME_BY_EXTENSION[$extension] ?? [], true)) {
            return false;
        }

        return match ($extension) {
            'docx' => $this->zipContains($path, 'word/'),
            'xlsx' => $this->zipContains($path, 'xl/'),
            'pptx' => $this->zipContains($path, 'ppt/'),
            'epub' => $this->isEpub($path),
            default => true,
        };
    }
```

## zipContains

Visibilidad: `private`. Líneas originales 200–220.

```php
    private function zipContains(string $path, string $prefix): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (str_starts_with((string) $zip->getNameIndex($index), $prefix)) {
                    return true;
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }
```

## isEpub

Visibilidad: `private`. Líneas originales 222–236.

```php
    private function isEpub(string $path): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        try {
            return trim((string) $zip->getFromName('mimetype')) === 'application/epub+zip';
        } finally {
            $zip->close();
        }
    }
```

## safeRelativePath

Visibilidad: `private`. Líneas originales 238–251.

```php
    private function safeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('La ruta de almacenamiento no es válida.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('La ruta de almacenamiento no es válida.');
            }
        }

        return $path;
    }
```

## invalid

Visibilidad: `private`. Líneas originales 253–256.

```php
    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['archivo' => [$message]]);
    }
```
