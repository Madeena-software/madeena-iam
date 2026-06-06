<?php

namespace App\Http\Middleware;

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
        $clientId = $request->input('client_id');

        if ($clientId && $request->user()) {
            $user = $request->user();

            if ($user->status !== 'approved') {
                return response('Your account is not approved or is suspended.', 403);
            }

            $pivot = $user->clients()->wherePivot('client_id', $clientId)->first();

            if (!$pivot || $pivot->pivot->is_blocked) {
                return response('You are not authorized to access this application.', 403);
            }
        }

        return $next($request);
    }
}
