<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/** Ninguna ruta de la API acepta archivos vacíos: 0 bytes, o sólo espacios y saltos de línea. */
class RejectEmptyUploads
{
    /** Más allá de este tamaño no vale la pena leer el archivo para ver si sólo trae espacios. */
    private const BLANK_CHECK_BYTES = 4096;

    public function handle(Request $request, Closure $next): Response
    {
        foreach (Arr::dot($request->allFiles()) as $field => $file) {
            if ($file instanceof UploadedFile && $file->isValid() && $this->isEmpty($file)) {
                $message = "El archivo «{$file->getClientOriginalName()}» está vacío. Revísalo y vuelve a subirlo.";

                return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
            }
        }

        return $next($request);
    }

    private function isEmpty(UploadedFile $file): bool
    {
        $size = (int) $file->getSize();
        if ($size === 0) {
            return true;
        }
        if ($size > self::BLANK_CHECK_BYTES) {
            return false;
        }

        // Se descuenta la marca BOM que agregan algunos editores a un texto sin contenido.
        $content = (string) file_get_contents($file->getRealPath());

        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $content)) === '';
    }
}
