<?php

declare(strict_types=1);

namespace App\Http\Controllers\Oauth;

use App\Enums\UserStatus;
use App\Mail\NewUserRegistrationAdminMail;
use App\Models\AuthenticationLog;
use App\Models\OauthClient;
use App\Services\GeoIPService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Http\Controllers\AuthorizationController as PassportAuthorizationController;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class AuthorizationController extends PassportAuthorizationController
{
    /**
     * Authorize a client to access the user's account.
     */
    public function authorize(
        ServerRequestInterface $psrRequest,
        Request $request,
        ResponseInterface $psrResponse,
        AuthorizationViewResponse $viewResponse
    ): Response|AuthorizationViewResponse {
        $prompt = $request->string('prompt')->explode(' ')->map(trim(...))->filter()->values();

        if ($prompt->contains('none')) {
            // Validate the authorization request first to ensure client & redirect URI are valid
            $authRequest = $this->withErrorHandling(
                fn (): AuthorizationRequestInterface => $this->server->validateAuthorizationRequest($psrRequest),
                ($psrRequest->getQueryParams()['response_type'] ?? null) === 'token'
            );

            $redirectUri = $authRequest->getRedirectUri() ?? Arr::wrap($authRequest->getClient()->getRedirectUri())[0];
            $state = $authRequest->getState();

            // 1. Check if user is unauthenticated
            if ($this->guard->guest()) {
                $query = http_build_query([
                    'error' => 'login_required',
                    'state' => $state,
                ]);
                $separator = str_contains($redirectUri, '?') ? '&' : '?';

                return redirect()->to($redirectUri.$separator.$query);
            }

            // 2. User is authenticated. Verify permission to access the client_id
            $user = $this->guard->user();
            $clientId = $authRequest->getClient()->getIdentifier();

            $pivot = $user->clients()->wherePivot('client_id', $clientId)->first();

            if (! $pivot) {
                $user->clients()->attach($clientId, [
                    'status' => UserStatus::PENDING_APPROVAL->value,
                    'is_blocked' => false,
                ]);
                $pivot = $user->clients()->wherePivot('client_id', $clientId)->first();

                // Notify admins of new pending request
                $oauthClient = OauthClient::find($clientId);
                $roleExists = Role::where('name', 'super_admin')->exists();
                $admins = $roleExists ? \App\Models\User::role('super_admin')->get() : collect();
                if ($admins->isNotEmpty() && $oauthClient) {
                    Mail::to($admins)->queue(new NewUserRegistrationAdminMail($user, $oauthClient));
                }
            }

            $isPermitted = $pivot
                && $pivot->pivot->status === UserStatus::APPROVED
                && ! $pivot->pivot->is_blocked;

            if (! $isPermitted) {
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

                $query = http_build_query([
                    'error' => 'access_denied',
                    'state' => $state,
                ]);
                $separator = str_contains($redirectUri, '?') ? '&' : '?';

                return redirect()->to($redirectUri.$separator.$query);
            }

            // 3. User is authenticated and permitted. Bypass consent screen and approve request.
            $authRequest->setUser(new User($user->getAuthIdentifier()));

            return $this->approveRequest($authRequest, $psrResponse);
        }

        // Fall back to standard Passport behavior if prompt does not contain 'none'
        return parent::authorize($psrRequest, $request, $psrResponse, $viewResponse);
    }
}
