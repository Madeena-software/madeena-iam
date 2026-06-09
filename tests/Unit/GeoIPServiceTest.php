<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GeoIPService;
use PHPUnit\Framework\TestCase;

class GeoIPServiceTest extends TestCase
{
    public function test_resolve_location_for_local_ips(): void
    {
        $localIps = ['127.0.0.1', '::1', '', null, '0'];

        foreach ($localIps as $ip) {
            $result = GeoIPService::resolveLocation($ip);

            $this->assertIsArray($result);
            $this->assertEquals('Localhost', $result['city']);
            $this->assertEquals('Local Network', $result['country']);
            $this->assertEquals('UTC', $result['timezone']);
        }
    }

    public function test_resolve_location_for_external_ips(): void
    {
        $externalIps = ['8.8.8.8', '192.168.1.100', '200.100.50.25'];

        foreach ($externalIps as $ip) {
            $result = GeoIPService::resolveLocation($ip);

            $this->assertIsArray($result);
            $this->assertEquals('Jakarta', $result['city']);
            $this->assertEquals('Indonesia', $result['country']);
            $this->assertEquals('Asia/Jakarta', $result['timezone']);
        }
    }
}
