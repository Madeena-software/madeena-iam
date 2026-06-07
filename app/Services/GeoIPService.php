<?php

declare(strict_types=1);

namespace App\Services;

class GeoIPService
{
    /**
     * Resolve location information from an IP address.
     */
    public static function resolveLocation(?string $ip): ?array
    {
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            return [
                'city' => 'Localhost',
                'country' => 'Local Network',
                'timezone' => 'UTC',
            ];
        }

        // Mock GeoIP lookup for external IPs in local/test environments
        return [
            'city' => 'Jakarta',
            'country' => 'Indonesia',
            'timezone' => 'Asia/Jakarta',
        ];
    }
}
