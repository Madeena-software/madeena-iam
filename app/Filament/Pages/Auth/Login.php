<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * Override rateLimit to dynamically increase limit in local/testing environments.
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null)
    {
        if (app()->environment('local', 'testing')) {
            $maxAttempts = 1000;
        }

        parent::rateLimit($maxAttempts, $decaySeconds, $method, $component);
    }
}
