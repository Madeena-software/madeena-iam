<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\AuthenticationLog;
use App\Services\GeoIPService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckClientAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $prompt = $request->string('prompt')->explode(' ')->map(trim(...))->filter()->values();
        if ($prompt->contains('none')) {
            return $next($request);
        }

        $clientId = $request->input('client_id');

        if ($clientId && $request->user()) {
            $user = $request->user();

            $pivot = $user->clients()->wherePivot('client_id', $clientId)->first();

            if (! $pivot) {
                AuthenticationLog::create([
                    'authenticatable_type' => get_class($user),
                    'authenticatable_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'login_at' => now(),
                    'login_successful' => false,
                    'client_id' => $clientId,
                    'status' => 'blocked_app',
                    'auth_type' => $request->input('auth_type', 'password'),
                    'location' => GeoIPService::resolveLocation($request->ip()),
                ]);

                return response('You are not authorized to access this application.', 403);
            }

            if ($pivot->pivot->status !== UserStatus::APPROVED) {
                AuthenticationLog::create([
                    'authenticatable_type' => get_class($user),
                    'authenticatable_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'login_at' => now(),
                    'login_successful' => false,
                    'client_id' => $clientId,
                    'status' => 'blocked_app',
                    'auth_type' => $request->input('auth_type', 'password'),
                    'location' => GeoIPService::resolveLocation($request->ip()),
                ]);

                return response('Your account is not approved or is suspended for this application.', 403);
            }

            if ($pivot->pivot->is_blocked) {
                AuthenticationLog::create([
                    'authenticatable_type' => get_class($user),
                    'authenticatable_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'login_at' => now(),
                    'login_successful' => false,
                    'client_id' => $clientId,
                    'status' => 'blocked_app',
                    'auth_type' => $request->input('auth_type', 'password'),
                    'location' => GeoIPService::resolveLocation($request->ip()),
                ]);

                return response('You are not authorized to access this application.', 403);
            }
        }

        return $next($request);
    }
}
