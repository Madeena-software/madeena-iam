<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    /**
     * Test the user agent parsing logic in Session model's device_details accessor.
     */
    public function test_device_details_accessor_parses_user_agents(): void
    {
        $testCases = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1' => [
                'browser' => 'Safari',
                'operating_system' => 'iPhone',
                'device' => 'Mobile',
                'description' => 'Safari on iPhone',
            ],
            'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1' => [
                'browser' => 'Safari',
                'operating_system' => 'iPad',
                'device' => 'Tablet',
                'description' => 'Safari on iPad',
            ],
            'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36' => [
                'browser' => 'Chrome',
                'operating_system' => 'Android',
                'device' => 'Mobile',
                'description' => 'Chrome on Android',
            ],
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0' => [
                'browser' => 'Edge',
                'operating_system' => 'Windows',
                'device' => 'Desktop',
                'description' => 'Edge on Windows',
            ],
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:124.0) Gecko/20100101 Firefox/124.0' => [
                'browser' => 'Firefox',
                'operating_system' => 'macOS',
                'device' => 'Desktop',
                'description' => 'Firefox on macOS',
            ],
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 OPR/109.0.0.0' => [
                'browser' => 'Opera',
                'operating_system' => 'Linux',
                'device' => 'Desktop',
                'description' => 'Opera on Linux',
            ],
            'Unknown User Agent' => [
                'browser' => 'Unknown Browser',
                'operating_system' => 'Unknown OS',
                'device' => 'Desktop',
                'description' => 'Unknown Browser on Unknown OS',
            ],
        ];

        foreach ($testCases as $userAgent => $expected) {
            $session = new Session(['user_agent' => $userAgent]);
            $details = $session->device_details;

            $this->assertEquals($expected['browser'], $details['browser']);
            $this->assertEquals($expected['operating_system'], $details['operating_system']);
            $this->assertEquals($expected['device'], $details['device']);
            $this->assertEquals($expected['description'], $details['description']);
        }
    }
}
