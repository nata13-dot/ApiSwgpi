<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseBackupController extends Controller
{
    public function index(DatabaseBackupService $backups)
    {
        $backups->housekeeping();
        $latestVerification = DB::table('verificaciones_restauracion as verification')
            ->select('verification.*')
            ->whereRaw('verification.id = (SELECT MAX(v2.id) FROM verificaciones_restauracion v2 WHERE v2.respaldo_id = verification.respaldo_id)');

        return response()->json([
            'data' => DB::table('respaldos_base_datos as backup')
                ->leftJoin('usuarios as actor', 'actor.id', '=', 'backup.creado_por')
                ->leftJoinSub($latestVerification, 'latest_verification', function ($join) {
                    $join->on('latest_verification.respaldo_id', '=', 'backup.id');
                })
                ->select([
                    'backup.id', 'backup.creado_por', 'backup.origen', 'backup.estado',
                    'backup.nombre_archivo', 'backup.tamano_bytes', 'backup.checksum_sha256',
                    'backup.mensaje_error', 'backup.creado_en',
                    'backup.eliminado_en',
                    'actor.nombres as actor_nombres', 'actor.apellido_paterno as actor_apellido',
                    'latest_verification.estado as estado_verificacion',
                    'latest_verification.tablas_encontradas',
                    'latest_verification.filas_verificadas',
                    'latest_verification.mensaje_error as error_verificacion',
                    'latest_verification.creado_en as verificado_en',
                ])
                ->whereNull('backup.eliminado_en')
                ->orderByDesc('backup.id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request, DatabaseBackupService $backups)
    {
        // Se genera en segundo plano: la pantalla consulta la lista hasta que deja de estar "en_proceso".
        $running = DB::table('respaldos_base_datos')->where('estado', 'en_proceso')
            ->where('creado_en', '>', now()->subMinutes(DatabaseBackupService::STALE_MINUTES))->exists();
        abort_if($running, 409, 'Ya se está generando un respaldo; espera a que termine.');
        $backup = $backups->startCreate('manual', $request->user('api')?->id);

        return response()->json(['message' => 'Generando el respaldo. Te avisaremos al terminar.', 'backup' => $backup], 202);
    }

    public function download(int $backup)
    {
        $record = DB::table('respaldos_base_datos')->where('id', $backup)->where('estado', 'completado')->whereNull('eliminado_en')->first();
        abort_if(!$record || !$record->ruta_privada || !is_file($record->ruta_privada), 404, 'El archivo del respaldo ya no está en el servidor.');
        abort_unless(is_readable($record->ruta_privada), 409, 'El archivo existe pero el servidor web no tiene permiso para leerlo (lo generó otro usuario del sistema).');
        abort_unless(hash_equals((string) $record->checksum_sha256, hash_file('sha256', $record->ruta_privada)), 409, 'El archivo no coincide con su checksum SHA-256: está alterado o dañado.');

        return response()->download($record->ruta_privada, $record->nombre_archivo, [
            'Content-Type' => 'application/gzip',
            'X-Checksum-SHA256' => $record->checksum_sha256,
        ]);
    }

    public function verify(Request $request, int $backup, DatabaseBackupService $backups)
    {
        try {
            $verification = $backups->startVerify($backup, $request->user('api')?->id);
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'No se pudo iniciar la prueba de restauración.');
        }

        return response()->json(['message' => 'Prueba de restauración en curso. Te avisaremos al terminar.', 'verification' => $verification], 202);
    }

    /**
     * El servicio ya dejó el intento fallido en el historial; aquí se devuelve el motivo en lugar del
     * "Server Error" genérico de producción. Los errores de SQL se resumen para no exponer consultas.
     */
    private function failure(\Throwable $exception, string $title)
    {
        report($exception);
        $detail = $exception instanceof \Illuminate\Database\QueryException
            ? 'Error de base de datos ('.$exception->getCode().').'
            : $exception->getMessage();

        // El servicio ya empieza con "No se pudo …": no se repite el título.
        $message = str_starts_with($detail, 'No se pudo') ? $detail : "{$title} {$detail}";

        return response()->json(['message' => $message, 'error' => $message], 422);
    }

    public function health(Request $request, DatabaseBackupService $backups)
    {
        $days = $request->has('retention_days') ? (int) $request->integer('retention_days') : null;
        return response()->json($backups->storageStatus($days));
    }

    public function cleanup(Request $request, DatabaseBackupService $backups)
    {
        $validated = $request->validate([
            'retention_days' => 'required|integer|min:7|max:365',
            'confirmation' => 'required|string',
        ]);
        $expected = "DEPURAR RESPALDOS ANTERIORES A {$validated['retention_days']} DIAS";
        abort_unless(hash_equals($expected, $validated['confirmation']), 422, 'La frase de confirmación no coincide.');

        return response()->json([
            'message' => 'Política de retención aplicada.',
            'result' => $backups->cleanup((int) $validated['retention_days'], (string) $request->user('api')->id),
        ]);
    }
}
