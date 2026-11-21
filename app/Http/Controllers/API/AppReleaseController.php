<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Actualización de la app Android fuera de Play Store. El administrador general publica cada APK; la app
 * consulta /app/latest al abrir y, si hay una versión mayor, la descarga desde /app/download/{id}.
 * Android sólo instala el APK si está firmado con la misma llave que la app instalada.
 */
class AppReleaseController extends Controller
{
    private const DISK = 'local';
    private const DIR = 'app-releases';

    /** Pública: la consulta también se hace antes de iniciar sesión. */
    public function latest(Request $request)
    {
        if (!SchemaCache::hasTable('app_versiones')) return response()->json(['data' => null]);
        $current = (int) $request->query('current', 0);
        $latest = DB::table('app_versiones')->where('activa', true)->orderByDesc('version_code')->first();
        if (!$latest) return response()->json(['data' => null]);
        // Obligatoria si alguna versión obligatoria publicada es mayor que la instalada.
        $minimum = (int) DB::table('app_versiones')->where('activa', true)->where('obligatoria', true)->max('version_code');
        // Aunque falten varias versiones, sólo se instala la última: se muestran sólo sus novedades.
        $notes = trim((string) $latest->notas) !== '' ? [['version' => $latest->version_name, 'notas' => $latest->notas]] : [];

        return response()->json(['data' => [
            'id' => $latest->id,
            'version_code' => (int) $latest->version_code,
            'version_name' => $latest->version_name,
            'notas' => $notes,
            'obligatoria' => $current > 0 && $current < $minimum,
            'disponible' => $current > 0 ? $current < (int) $latest->version_code : true,
            'tamano_bytes' => (int) $latest->tamano_bytes,
            'sha256' => $latest->sha256,
            'url' => url("/api/app/download/{$latest->id}"),
            'publicada_en' => Carbon::parse($latest->publicada_en)->toIso8601String(),
        ]]);
    }

    /** Pública: el APK no es secreto y se descarga sin sesión (también desde la web). */
    public function download(int $id)
    {
        $release = DB::table('app_versiones')->where('id', $id)->where('activa', true)->first();
        abort_unless($release && Storage::disk(self::DISK)->exists($release->ruta), 404, 'Versión no disponible.');
        DB::table('app_versiones')->where('id', $id)->increment('descargas');

        return Storage::disk(self::DISK)->download($release->ruta, "SGPI-{$release->version_name}.apk", [
            'Content-Type' => 'application/vnd.android.package-archive',
            'X-Content-SHA256' => $release->sha256,
        ]);
    }

    public function index()
    {
        return response()->json(['data' => DB::table('app_versiones')->orderByDesc('version_code')->get()->map(fn ($v) => [
            'id' => $v->id, 'version_code' => (int) $v->version_code, 'version_name' => $v->version_name, 'notas' => $v->notas,
            'obligatoria' => (bool) $v->obligatoria, 'activa' => (bool) $v->activa, 'tamano_bytes' => (int) $v->tamano_bytes,
            'sha256' => $v->sha256, 'descargas' => (int) $v->descargas, 'publicada_en' => Carbon::parse($v->publicada_en)->toIso8601String(),
            'url' => url("/api/app/download/{$v->id}"),
        ])]);
    }

    public function store(Request $request)
    {
        return $this->publish($request, (string) auth('api')->id());
    }

    /**
     * Publicación automática desde el script de compilación (npm run release:android). Se autentica con una
     * llave de publicación (php artisan sgpi:release-token), no con una sesión de usuario.
     */
    public function publishWithToken(Request $request)
    {
        $token = (string) $request->header('X-SGPI-Release-Token', '');
        abort_if(strlen($token) < 32 || !SchemaCache::hasTable('app_llaves_publicacion'), 401, 'Llave de publicación no válida.');
        $key = DB::table('app_llaves_publicacion')->where('huella', hash('sha256', $token))->whereNull('revocada_en')->first();
        abort_unless($key, 401, 'Llave de publicación no válida.');
        DB::table('app_llaves_publicacion')->where('id', $key->id)->update(['ultimo_uso' => now()]);

        return $this->publish($request, 'auto:'.mb_substr($key->nombre, 0, 15));
    }

    private function publish(Request $request, string $by)
    {
        $data = $request->validate([
            'apk' => 'required|file|max:204800',
            'version_code' => 'required|integer|min:1|unique:app_versiones,version_code',
            'version_name' => ['required', 'string', 'max:40', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'notas' => 'nullable|string|max:3000',
            'obligatoria' => 'sometimes|boolean',
        ]);
        $file = $request->file('apk');
        // Un APK es un ZIP con AndroidManifest.xml: se rechaza cualquier otro archivo.
        $path = $file->getRealPath();
        abort_unless(strtolower($file->getClientOriginalExtension()) === 'apk' && $this->looksLikeApk($path), 422, 'El archivo no es un APK válido.');
        $newest = (int) DB::table('app_versiones')->max('version_code');
        abort_if($data['version_code'] <= $newest, 422, "El número de versión debe ser mayor que {$newest}.");
        $stored = Storage::disk(self::DISK)->putFileAs(self::DIR, $file, sprintf('sgpi-%d-%s.apk', $data['version_code'], bin2hex(random_bytes(6))));
        $id = DB::table('app_versiones')->insertGetId([
            'version_code' => $data['version_code'], 'version_name' => $data['version_name'], 'notas' => $data['notas'] ?? null,
            'obligatoria' => (bool) ($data['obligatoria'] ?? false), 'activa' => true, 'ruta' => $stored,
            'tamano_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $path),
            'publicada_por' => mb_substr($by, 0, 20), 'publicada_en' => now(),
        ]);

        return response()->json(['message' => "Versión {$data['version_name']} publicada. Las apps la ofrecerán al abrirse.", 'id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['notas' => 'sometimes|nullable|string|max:3000', 'obligatoria' => 'sometimes|boolean', 'activa' => 'sometimes|boolean']);
        abort_unless(DB::table('app_versiones')->where('id', $id)->exists(), 404);
        DB::table('app_versiones')->where('id', $id)->update($data);

        return response()->json(['message' => 'Versión actualizada.']);
    }

    public function destroy(int $id)
    {
        $release = DB::table('app_versiones')->where('id', $id)->first();
        abort_unless($release, 404);
        Storage::disk(self::DISK)->delete($release->ruta);
        DB::table('app_versiones')->where('id', $id)->delete();

        return response()->json(['message' => 'Versión eliminada.']);
    }

    private function looksLikeApk(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) return str_starts_with((string) file_get_contents($path, false, null, 0, 4), "PK\x03\x04");
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return false;
        $ok = $zip->locateName('AndroidManifest.xml') !== false && $zip->locateName('classes.dex') !== false;
        $zip->close();

        return $ok;
    }
}
