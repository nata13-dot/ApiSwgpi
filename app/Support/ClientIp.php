<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * IP real del dispositivo que hizo la petición. La API está detrás de un túnel/proxy local: PHP ve 127.0.0.1 y la IP
 * del cliente llega en cabeceras. Sólo se leen esas cabeceras cuando quien conecta es un proxy local o de red
 * privada (desde internet nadie puede falsearlas). Orden: CF-Connecting-IP (Cloudflare), X-Real-IP (nginx) y
 * X-Forwarded-For de derecha a izquierda (la primera IP pública que agregó el proxy de confianza).
 */
class ClientIp
{
    public static function of(?Request $request = null): ?string
    {
        $request ??= request();
        $peer = (string) $request->server('REMOTE_ADDR');
        if (!self::isLocal($peer)) {
            return $peer ?: $request->ip();
        }
        foreach (['CF-Connecting-IP', 'X-Real-IP'] as $header) {
            $value = trim((string) $request->headers->get($header));
            if (self::valid($value) && !self::isLocal($value)) return $value;
        }
        $chain = array_reverse(array_filter(array_map('trim', explode(',', (string) $request->headers->get('X-Forwarded-For')))));
        foreach ($chain as $candidate) {
            if (self::valid($candidate) && !self::isLocal($candidate)) return $candidate;
        }
        // Red local (pruebas o equipos dentro del campus): la primera IP válida de la cadena o la del par.
        foreach ($chain as $candidate) {
            if (self::valid($candidate)) return $candidate;
        }

        return $request->ip() ?: $peer;
    }

    /** Cabeceras recibidas, para comprobar desde Auditoría que la IP se detecta bien. */
    public static function diagnostics(?Request $request = null): array
    {
        $request ??= request();

        return [
            'ip' => self::of($request),
            'peer' => $request->server('REMOTE_ADDR'),
            'cf_connecting_ip' => $request->headers->get('CF-Connecting-IP'),
            'x_real_ip' => $request->headers->get('X-Real-IP'),
            'x_forwarded_for' => $request->headers->get('X-Forwarded-For'),
            'user_agent' => $request->userAgent(),
            'device' => DeviceLabel::from($request->userAgent(), $request->headers->get('X-SGPI-Client')),
        ];
    }

    private static function valid(string $ip): bool
    {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private static function isLocal(string $ip): bool
    {
        if (!self::valid($ip)) return false;

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
