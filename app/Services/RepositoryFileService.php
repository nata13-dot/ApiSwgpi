<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\RepositoryDocument;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class RepositoryFileService
{
    private const MAX_VERSION_ATTEMPTS = 3;

    public function __construct(private readonly StoredFileService $files) {}

    /**
     * @param  callable(): RepositoryDocument  $documentOperation
     * @return array{document:RepositoryDocument,version:DocumentVersion}
     */
    public function persist(
        UploadedFile $file,
        User $user,
        callable $documentOperation,
        ?array $allowedExtensions = null,
        ?int $maxSizeMb = null,
        string $directory = 'repository'
    ): array {
        $prepared = null;
        $stored = null;

        try {
            $prepared = $this->files->prepare($file, $allowedExtensions, $maxSizeMb);
            $stored = $this->files->promote($prepared, $directory);

            for ($attempt = 1; $attempt <= self::MAX_VERSION_ATTEMPTS; $attempt++) {
                try {
                    return $this->persistVersion($documentOperation, $user, $prepared, $stored);
                } catch (QueryException $exception) {
                    if (! $this->isVersionCollision($exception) || $attempt === self::MAX_VERSION_ATTEMPTS) {
                        throw $exception;
                    }
                }
            }

            throw new RuntimeException('No se pudo asignar un número de versión único al documento.');
        } catch (Throwable $exception) {
            $this->files->discardStored($stored);
            $this->files->discardPrepared($prepared);
            throw $exception;
        }
    }

    protected function persistVersion(
        callable $documentOperation,
        User $user,
        array $prepared,
        array $stored
    ): array {
        return DB::transaction(function () use ($documentOperation, $user, $prepared, $stored): array {
            $document = $documentOperation();
            if (! $document instanceof RepositoryDocument || ! $document->exists) {
                throw new RuntimeException('La operación no produjo un documento persistido.');
            }

            RepositoryDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $versionNumber = ((int) DocumentVersion::query()
                ->where('documento_id', $document->id)
                ->lockForUpdate()
                ->max('numero_version')) + 1;

            $attributes = [
                'document_id' => $document->id,
                'version_number' => $versionNumber,
                'file_name' => $prepared['file_name'],
                'file_path' => $stored['path'],
                'extension' => $prepared['extension'],
                'mime_type' => $prepared['mime_type'],
                'size_bytes' => $prepared['size_bytes'],
                'descripcion' => $versionNumber === 1 ? 'Versión inicial' : 'Archivo actualizado',
                'uploaded_by' => $user->id,
                'created_at' => now(),
            ];
            if (Schema::hasColumn('documento_versiones', 'nombre_original')) {
                $attributes['original_name'] = $prepared['original_name'];
            }
            if (Schema::hasColumn('documento_versiones', 'disco')) {
                $attributes['disk'] = $stored['disk'];
            }
            if (Schema::hasColumn('documento_versiones', 'checksum_sha256')) {
                $attributes['checksum'] = $prepared['checksum_sha256'];
            }

            $version = DocumentVersion::create($attributes);

            return [
                'document' => $document->fresh(['latestVersion']),
                'version' => $version->fresh(),
            ];
        }, 1);
    }

    private function isVersionCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && ($driverCode === '1062'
                || str_contains($message, 'documento_versiones')
                || str_contains($message, 'numero_version'));
    }
}
