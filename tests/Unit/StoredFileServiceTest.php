<?php

namespace Tests\Unit;

use App\Services\StoredFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class StoredFileServiceTest extends TestCase
{
    private StoredFileService $service;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('legacy_public');
        config([
            'uploads.temporary_disk' => 'local',
            'uploads.write_disk' => 'legacy_public',
            'uploads.max_size_mb' => 50,
        ]);
        $this->service = app(StoredFileService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function test_prepares_promotes_and_hashes_a_valid_pdf(): void
    {
        $prepared = $this->service->prepare($this->upload('informe final.pdf', "%PDF-1.7\ncontenido"), ['pdf']);
        $stored = $this->service->promote($prepared, 'deliverables/project-1');

        $this->assertSame('informe final.pdf', $prepared['original_name']);
        $this->assertSame(hash('sha256', "%PDF-1.7\ncontenido"), $prepared['checksum_sha256']);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}\.pdf$/', $prepared['file_name']);
        Storage::disk('legacy_public')->assertExists($stored['path']);
        Storage::disk('local')->assertMissing($prepared['temporary_path']);
    }

    public function test_rejects_php_and_dangerous_double_extensions(): void
    {
        foreach (['payload.php', 'payload.php.pdf'] as $name) {
            try {
                $this->service->prepare($this->upload($name, "%PDF-1.7\ncontenido"), ['pdf', 'php']);
                $this->fail("{$name} debió rechazarse.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('archivo', $exception->errors());
            }
        }
    }

    public function test_rejects_mime_mismatch_and_compressed_file_renamed_as_pdf(): void
    {
        foreach ([
            $this->upload('texto.pdf', 'esto no es un pdf'),
            $this->upload('archivo.pdf', $this->zipBytes()),
        ] as $upload) {
            $this->expectValidationFailure(fn () => $this->service->prepare($upload, ['pdf']));
        }
    }

    public function test_accepts_valid_zip_rar_and_7z_signatures(): void
    {
        $cases = [
            ['archivo.zip', $this->zipBytes(), 'zip'],
            ['archivo.rar', "Rar!\x1A\x07\x01\x00", 'rar'],
            ['archivo.7z', "7z\xBC\xAF\x27\x1C\x00\x04", '7z'],
        ];

        foreach ($cases as [$name, $contents, $extension]) {
            $prepared = $this->service->prepare($this->upload($name, $contents), [$extension]);
            $this->assertSame($extension, $prepared['extension']);
            $this->service->discardPrepared($prepared);
        }
    }

    private function expectValidationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('La validación debió fallar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('archivo', $exception->errors());
        }
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'swgpi-upload-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }

    private function zipBytes(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'swgpi-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'contenido');
        $zip->close();
        $contents = file_get_contents($path);
        @unlink($path);

        return $contents;
    }
}
