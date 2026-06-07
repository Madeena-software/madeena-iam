<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuthenticationLog;
use App\Services\GeoIPService;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $user = $event->user;
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        $clientId = $this->getClientId();

        AuthenticationLog::create([
            'authenticatable_type' => $user ? get_class($user) : null,
            'authenticatable_id' => $user ? $user->id : null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'login_at' => now(),
            'login_successful' => false,
            'client_id' => $clientId,
            'status' => 'failed_password',
            'auth_type' => request()->input('auth_type', 'password'),
            'location' => GeoIPService::resolveLocation($ip),
        ]);
    }

    /**
     * Resolve the client ID from request inputs or session's intended URL.
     */
    protected function getClientId(): ?string
    {
        if ($clientId = request()->input('client_id')) {
            return $clientId;
        }

        if ($intendedUrl = session()->get('url.intended')) {
            $parsed = parse_url($intendedUrl);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                if (isset($queryParams['client_id'])) {
                    return $queryParams['client_id'];
                }
            }
        }

        return null;
    }
}
