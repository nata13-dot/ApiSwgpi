<?php

namespace App\Support;

/** Descripción corta del dispositivo a partir del agente de usuario: «App SGPI 1.1.46 · Android 14 · Redmi Note 13». */
class DeviceLabel
{
    public static function from(?string $agent, ?string $client = null): string
    {
        $agent = (string) $agent;
        if ($agent === '') return 'Desconocido';
        $parts = [];
        if ($client && preg_match('#^app/([\w.\-]+)#i', $client, $match)) $parts[] = 'App SGPI '.$match[1];
        elseif (str_contains($agent, '; wv)')) $parts[] = 'App SGPI';

        if (preg_match('#Android ([\d.]+);\s*([^;)]+)#', $agent, $match)) {
            $parts[] = 'Android '.$match[1];
            $model = trim(preg_replace('#\s*Build/.*$#', '', $match[2]));
            if ($model !== '' && strcasecmp($model, 'K') !== 0) $parts[] = $model;
        } elseif (preg_match('#(iPhone|iPad).*OS ([\d_]+)#', $agent, $match)) {
            $parts[] = $match[1].' iOS '.str_replace('_', '.', $match[2]);
        } elseif (str_contains($agent, 'Windows')) {
            $parts[] = 'Windows';
        } elseif (str_contains($agent, 'Mac OS X')) {
            $parts[] = 'macOS';
        } elseif (str_contains($agent, 'Linux')) {
            $parts[] = 'Linux';
        }
        if (!str_contains($agent, '; wv)') && !$client) {
            $browser = match (true) {
                str_contains($agent, 'Edg/') => 'Edge',
                str_contains($agent, 'OPR/') => 'Opera',
                str_contains($agent, 'Firefox/') => 'Firefox',
                str_contains($agent, 'Chrome/') => 'Chrome',
                str_contains($agent, 'Safari/') => 'Safari',
                default => null,
            };
            if ($browser) $parts[] = $browser;
        }

        return $parts ? implode(' · ', $parts) : mb_substr($agent, 0, 60);
    }
}
