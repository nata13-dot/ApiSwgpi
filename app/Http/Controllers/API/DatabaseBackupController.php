<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseBackupController extends Controller
{
    public function index()
    {
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
        return response()->json([
            'message' => 'Respaldo generado correctamente.',
            'backup' => $backups->create('manual', $request->user('api')?->id),
        ], 201);
    }

    public function download(int $backup)
    {
        $record = DB::table('respaldos_base_datos')->where('id', $backup)->where('estado', 'completado')->first();
        abort_if(!$record || !$record->ruta_privada || !is_file($record->ruta_privada), 404, 'Respaldo no encontrado.');

        return response()->download($record->ruta_privada, $record->nombre_archivo, [
            'Content-Type' => 'application/gzip',
            'X-Checksum-SHA256' => $record->checksum_sha256,
        ]);
    }

    public function verify(Request $request, int $backup, DatabaseBackupService $backups)
    {
        return response()->json([
            'message' => 'El respaldo puede restaurarse correctamente.',
            'verification' => $backups->verify($backup, $request->user('api')?->id),
        ]);
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
