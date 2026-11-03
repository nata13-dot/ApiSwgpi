<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Informes que tardan más de lo que espera una petición web. El PDF se genera después de responder y el
 * cliente consulta hasta que está listo. Los archivos son privados, de quien los pidió, y se borran al
 * descargarlos o a la hora como máximo.
 */
class ReportJobService
{
    private const MAX_AGE_SECONDS = 3600;
    /** Si pasado este tiempo sigue "generando", el proceso murió sin poder avisar. */
    private const MAX_GENERATION_SECONDS = 330;

    public function start(string $userId, string $filename, \Closure $generate): string
    {
        $this->purge();
        $id = (string) Str::uuid();
        $this->write($id, ['user_id' => $userId, 'filename' => $filename, 'status' => 'generating', 'created_at' => time()]);

        // Un error fatal (memoria, tiempo) no pasa por catch: se marca al terminar el proceso.
        $finished = false;
        register_shutdown_function(function () use (&$finished, $id) {
            if (!$finished) $this->update($id, ['status' => 'failed']);
        });
        app()->terminating(function () use (&$finished, $id, $generate) {
            try {
                File::put($this->path($id, 'pdf'), $generate());
                chmod($this->path($id, 'pdf'), 0600);
                $this->update($id, ['status' => 'ready']);
            } catch (\Throwable $exception) {
                report($exception);
                $this->update($id, ['status' => 'failed']);
            } finally {
                $finished = true;
            }
        });

        return $id;
    }

    /** @return array{status: string, filename: string}|null null si no existe o es de otra persona */
    public function find(string $id, string $userId): ?array
    {
        $job = $this->read($id);
        if (!$job || (string) $job['user_id'] !== $userId) {
            return null;
        }
        if ($job['status'] === 'generating' && time() - (int) $job['created_at'] > self::MAX_GENERATION_SECONDS) {
            $job['status'] = 'failed';
        }

        return $job;
    }

    public function pdfPath(string $id): string
    {
        return $this->path($id, 'pdf');
    }

    public function forget(string $id): void
    {
        File::delete([$this->path($id, 'json'), $this->path($id, 'pdf')]);
    }

    private function purge(): void
    {
        File::ensureDirectoryExists($this->directory(), 0700);
        foreach (File::files($this->directory()) as $file) {
            if (time() - $file->getMTime() > self::MAX_AGE_SECONDS) {
                File::delete($file->getPathname());
            }
        }
    }

    private function directory(): string
    {
        return storage_path('app/private/report-jobs');
    }

    private function path(string $id, string $extension): string
    {
        return $this->directory().'/'.$id.'.'.$extension;
    }

    private function read(string $id): ?array
    {
        $path = $this->path($id, 'json');

        return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    }

    private function write(string $id, array $job): void
    {
        File::put($this->path($id, 'json'), json_encode($job), true);
        chmod($this->path($id, 'json'), 0600);
    }

    private function update(string $id, array $changes): void
    {
        if ($job = $this->read($id)) {
            $this->write($id, $changes + $job);
        }
    }
}
