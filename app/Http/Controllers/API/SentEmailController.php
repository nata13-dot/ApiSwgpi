<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Registro de correos enviados para la administración general. El servicio de correo no conserva asunto ni contenido. */
class SentEmailController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|in:queued,sending,sent,failed',
            'category' => 'nullable|string|max:30',
            'search' => 'nullable|string|max:120',
        ]);
        $key = config('mail.mailers.swgpi.admin_key');
        if (!$key) {
            return response()->json(['message' => 'Falta configurar SWGPI_MAILER_ADMIN_KEY en la API.'], 503);
        }

        $client = Http::withToken($key)->acceptJson()->timeout(5)->connectTimeout(2)->baseUrl(rtrim((string) config('mail.mailers.swgpi.endpoint'), '/'));
        try {
            $history = $client->get('/v1/emails', $filters + ['per_page' => 25]);
            $stats = $client->get('/v1/stats');
        } catch (ConnectionException) {
            return response()->json(['message' => 'El servicio de correo no responde.'], 503);
        }
        if (!$history->successful() || !$stats->successful()) {
            return response()->json(['message' => 'El servicio de correo rechazó la consulta. Revisa la llave de administración.'], 503);
        }

        return response()->json($history->json() + ['stats' => $stats->json()]);
    }
}
