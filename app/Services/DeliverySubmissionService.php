<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\Delivery;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\RepositoryDocument;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DeliverySubmissionService
{
    private const MAX_VERSION_ATTEMPTS = 3;

    public function __construct(private readonly StoredFileService $files) {}

    /**
     * @return array{delivery:Delivery,document:RepositoryDocument,version:DocumentVersion}
     */
    public function submit(
        Deliverable $deliverable,
        Project $project,
        User $user,
        UploadedFile $file,
        ?array $allowedExtensions = null,
        ?int $maxSizeMb = null
    ): array {
        $this->authorizeContext($deliverable, $project, $user);

        $prepared = null;
        $stored = null;
        try {
            $prepared = $this->files->prepare($file, $allowedExtensions, $maxSizeMb);
            $stored = $this->files->promote(
                $prepared,
                sprintf(
                    'deliverables/career-%d/project-%d/deliverable-%d',
                    (int) $deliverable->carrera_id,
                    (int) $project->id,
                    (int) $deliverable->id
                )
            );

            for ($attempt = 1; $attempt <= self::MAX_VERSION_ATTEMPTS; $attempt++) {
                try {
                    return $this->persist($deliverable, $project, $user, $prepared, $stored);
                } catch (QueryException $exception) {
                    if (! $this->isVersionCollision($exception) || $attempt === self::MAX_VERSION_ATTEMPTS) {
                        throw $exception;
                    }
                }
            }

            throw new RuntimeException('No se pudo asignar un número de versión único.');
        } catch (Throwable $exception) {
            $this->files->discardStored($stored);
            $this->files->discardPrepared($prepared);
            throw $exception;
        }
    }

    protected function persist(
        Deliverable $deliverable,
        Project $project,
        User $user,
        array $prepared,
        array $stored
    ): array {
        return DB::transaction(function () use ($deliverable, $project, $user, $prepared, $stored): array {
            Deliverable::query()->whereKey($deliverable->id)->lockForUpdate()->firstOrFail();

            $delivery = Delivery::query()
                ->where('entregable_id', $deliverable->id)
                ->where('proyecto_id', $project->id)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                $document = RepositoryDocument::create([
                    'carrera_id' => $deliverable->carrera_id,
                    'project_id' => $project->id,
                    'nombre' => $deliverable->nombre,
                    'descripcion' => $deliverable->descripcion,
                    'autores' => trim($user->getFullName()) ?: (string) $user->id,
                    'document_category' => RepositoryDocument::CATEGORY_DELIVERABLE,
                    'visibility' => RepositoryDocument::VISIBILITY_PRIVATE,
                    'estado' => 'borrador',
                    'uploaded_by' => $user->id,
                    'activo' => true,
                ]);

                $delivery = Delivery::create([
                    'entregable_id' => $deliverable->id,
                    'proyecto_id' => $project->id,
                    'documento_id' => $document->id,
                    'enviado_por' => $user->id,
                    'entregado_en' => now(),
                ]);
            } else {
                $document = RepositoryDocument::query()->lockForUpdate()->find($delivery->documento_id);
                if (! $document) {
                    throw new RuntimeException('La entrega existente no tiene un documento válido asociado.');
                }
                $delivery->update([
                    'enviado_por' => $user->id,
                    'entregado_en' => now(),
                    'calificacion' => null,
                    'comentarios_docente' => null,
                ]);
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
                'descripcion' => $versionNumber === 1 ? 'Versión inicial' : 'Reentrega',
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
                'delivery' => $delivery->fresh(['document.latestVersion']),
                'document' => $document->fresh(['latestVersion']),
                'version' => $version->fresh(),
            ];
        }, 1);
    }

    private function authorizeContext(Deliverable $deliverable, Project $project, User $user): void
    {
        $deliverable->loadMissing('course');
        $sameCareer = (int) $deliverable->carrera_id === (int) $project->carrera_id;
        $sameGroup = $deliverable->course
            && (int) $deliverable->course->grupo_id === (int) $project->grupo_id;

        if (! $sameCareer || ! $sameGroup || ! $deliverable->activo || ! $project->activo) {
            throw ValidationException::withMessages([
                'project_id' => ['El proyecto no corresponde al entregable seleccionado.'],
            ]);
        }

        if ($user->canManageProjects()) {
            return;
        }

        $isStudentMember = (int) $user->perfil_id === 3
            && $project->students()->where('usuarios.id', $user->id)->exists();
        if (! $isStudentMember) {
            throw new AuthorizationException('No tienes autorización para entregar archivos en este proyecto.');
        }
    }

    private function isVersionCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && ($driverCode === '1062'
                || str_contains($message, 'uq_documento_version')
                || str_contains($message, 'documento_id, numero_version')
                || str_contains($message, 'documento_versiones.documento_id'));
    }
}
