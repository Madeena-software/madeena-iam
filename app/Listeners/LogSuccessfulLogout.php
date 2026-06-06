<?php

namespace App\Listeners;

use App\Models\AuthenticationLog;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
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
    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        $user = $event->user;
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        $log = AuthenticationLog::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->where('ip_address', $ip)
            ->where('user_agent', $userAgent)
            ->orderByDesc('login_at')
            ->first();

        if ($log) {
            $log->update(['logout_at' => now()]);
        }
    }
}
