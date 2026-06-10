<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class VersionTest extends TestCase
{
    public function test_version_trimming_logic(): void
    {
        $trimVersion = function (string $rawVersion): string {
            return ltrim(trim($rawVersion), 'vV');
        };

        $this->assertEquals('1.0.0', $trimVersion('v1.0.0'));
        $this->assertEquals('1.0.0', $trimVersion('V1.0.0'));
        $this->assertEquals('1.0.0', $trimVersion('  v1.0.0  '));
        $this->assertEquals('1.0.0-beta', $trimVersion('v1.0.0-beta'));
        $this->assertEquals('2.3.4', $trimVersion('2.3.4'));
    }

    public function test_app_version_config_is_set(): void
    {
        $this->assertNotEmpty(config('app.version'));
    }
}
