<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
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
                return response('You are not authorized to access this application.', 403);
            }

            if ($pivot->pivot->status !== UserStatus::APPROVED) {
                return response('Your account is not approved or is suspended for this application.', 403);
            }

            if ($pivot->pivot->is_blocked) {
                return response('You are not authorized to access this application.', 403);
            }
        }

        return $next($request);
    }
}
