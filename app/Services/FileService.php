<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

/**
 * Servicio para gestión de archivos y descargas
 * 
 * Maneja:
 * - Validación MIME types
 * - Almacenamiento seguro
 * - Descargas con validación de acceso
 */
class FileService
{
    const UPLOAD_PATH = 'entregas';
    const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip',
        'image/jpeg',
        'image/png',
    ];
    
    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    const MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    
    /**
     * Validar que un archivo es seguro para almacenar
     * 
     * @param \Illuminate\Http\UploadedFile $file Archivo a validar
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateFile($file): array
    {
        // Validar que el archivo existe
        if (!$file || !$file->isValid()) {
            return ['valid' => false, 'error' => 'Archivo inválido o corrupto'];
        }
        
        // Validar tamaño
        $maxSizeMb = (int) SystemSetting::valueFor('max_file_size_mb', 50);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            return [
                'valid' => false,
                'error' => "El archivo excede el tamaño máximo de {$maxSizeMb}MB"
            ];
        }
        
        // Validar MIME type
        $allowedExtensions = SystemSetting::valueFor('allowed_file_types', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip']);
        $allowedMimes = collect($allowedExtensions)
            ->flatMap(fn ($extension) => self::MIME_BY_EXTENSION[$extension] ?? [])
            ->unique()
            ->values()
            ->all();
        $mime = $file->getMimeType();
        if (!in_array($mime, $allowedMimes, true)) {
            return [
                'valid' => false,
                'error' => 'Tipo de archivo no permitido. Permitidos: ' . strtoupper(implode(', ', $allowedExtensions))
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Guardar un archivo de entregable
     * 
     * @param \Illuminate\Http\UploadedFile $file Archivo a guardar
     * @param int $deliverable_id ID del entregable
     * @param string $user_id ID del usuario que sube
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public static function storeDeliverableFile($file, int $deliverable_id, string $user_id): array
    {
        try {
            // Validar archivo
            $validation = self::validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'path' => null, 'error' => $validation['error']];
            }
            
            // Crear nombre único
            $timestamp = time();
            $nombre_original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $nombre_archivo = "{$deliverable_id}_{$user_id}_{$timestamp}.{$extension}";
            
            // Guardar en storage
            $path = Storage::disk('public')->putFileAs(
                self::UPLOAD_PATH,
                $file,
                $nombre_archivo
            );
            
            if (!$path) {
                return ['success' => false, 'path' => null, 'error' => 'Error al guardar el archivo'];
            }
            
            return ['success' => true, 'path' => $path, 'error' => null];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'path' => null,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generar respuesta de descarga para un entregable
     * 
     * @param Deliverable $deliverable Entregable a descargar
     * @return StreamedResponse|array Response o error
     * @throws \Symfony\Component\HttpFoundation\Exception\FileNotFoundException
     */
    public static function downloadDeliverable(Deliverable $deliverable): StreamedResponse
    {
        if (!$deliverable->archivo_path) {
            throw new Exception('El entregable no tiene archivo asociado');
        }
        
        if (!Storage::disk('public')->exists($deliverable->archivo_path)) {
            throw new Exception('El archivo no existe en el servidor');
        }
        
        return Storage::disk('public')->download($deliverable->archivo_path);
    }
    
    /**
     * Eliminar archivo de un entregable
     * 
     * @param string $file_path Ruta del archivo
     * @return bool true si se eliminó, false si error
     */
    public static function deleteFile(string $file_path): bool
    {
        try {
            if (Storage::disk('public')->exists($file_path)) {
                return Storage::disk('public')->delete($file_path);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtener URL pública de un archivo
     * 
     * @param string $file_path Ruta del archivo
     * @return string URL pública
     */
    public static function getPublicUrl(string $file_path): string
    {
        return Storage::disk('public')->url($file_path);
    }
}
