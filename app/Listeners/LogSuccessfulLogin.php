<?php

namespace App\Listeners;

use App\Models\AuthenticationLog;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
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
    public function handle(Login $event): void
    {
        $user = $event->user;
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        AuthenticationLog::create([
            'authenticatable_type' => get_class($user),
            'authenticatable_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'login_at' => now(),
        ]);
    }
}
