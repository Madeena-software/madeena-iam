<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Mail\NewUserRegistrationAdminMail;
use App\Models\AuthenticationLog;
use App\Models\OauthClient;
use App\Models\User;
use App\Services\GeoIPService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'client_id' => 'required|uuid',
            'client_secret' => 'required|string',
        ]);

        $client = OauthClient::where('id', $request->client_id)
            ->where('is_active', true)
            ->first();

        if (! $client || ! Hash::check($request->client_secret, $client->secret)) {
            AuthenticationLog::create([
                'authenticatable_type' => null,
                'authenticatable_id' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'login_successful' => false,
                'client_id' => $request->client_id,
                'status' => 'invalid_client',
                'auth_type' => $request->input('auth_type', 'password'),
                'location' => GeoIPService::resolveLocation($request->ip()),
            ]);

            return response()->json(['message' => 'Invalid client credentials'], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            AuthenticationLog::create([
                'authenticatable_type' => $user ? get_class($user) : null,
                'authenticatable_id' => $user ? $user->id : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'login_successful' => false,
                'client_id' => $client->id,
                'status' => 'failed_password',
                'auth_type' => $request->input('auth_type', 'password'),
                'location' => GeoIPService::resolveLocation($request->ip()),
            ]);

            return response()->json(['message' => 'Invalid user credentials'], 401);
        }

        // Verify client access in pivot table
        $clientUser = $user->clients()->wherePivot('client_id', $client->id)->first();
        if (! $clientUser) {
            AuthenticationLog::create([
                'authenticatable_type' => get_class($user),
                'authenticatable_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'login_successful' => false,
                'client_id' => $client->id,
                'status' => 'blocked_app',
                'auth_type' => $request->input('auth_type', 'password'),
                'location' => GeoIPService::resolveLocation($request->ip()),
            ]);

            return response()->json(['message' => 'User is not authorized for this application'], 403);
        }

        if ($clientUser->pivot->status !== UserStatus::APPROVED) {
            AuthenticationLog::create([
                'authenticatable_type' => get_class($user),
                'authenticatable_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'login_successful' => false,
                'client_id' => $client->id,
                'status' => 'blocked_app',
                'auth_type' => $request->input('auth_type', 'password'),
                'location' => GeoIPService::resolveLocation($request->ip()),
            ]);

            return response()->json(['message' => 'User account is not approved or is suspended for this application'], 403);
        }

        if ($clientUser->pivot->is_blocked) {
            AuthenticationLog::create([
                'authenticatable_type' => get_class($user),
                'authenticatable_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'login_successful' => false,
                'client_id' => $client->id,
                'status' => 'blocked_app',
                'auth_type' => $request->input('auth_type', 'password'),
                'location' => GeoIPService::resolveLocation($request->ip()),
            ]);

            return response()->json(['message' => 'User is blocked from accessing this application'], 403);
        }

        // Silent SSO Hook: Log the user into the web guard to establish session cookie
        Auth::guard('web')->login($user);

        // Issue a Personal Access Token or we can use Password Grant token
        // For simplicity and since we validated the client ourselves, we issue a personal access token
        // Or if we want an OAuth token explicitly tied to this client, we use Password Grant.
        // But since this is a custom API endpoint, a standard Passport token tied to the user is sufficient.
        // To tie it to a client, we should ideally use the internal token request.
        // Let's create a token.
        $tokenResult = $user->createToken($client->name);

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $clientUser->pivot->status->value,
            ],
            'sso_session_id' => session()->getId(),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'client_id' => 'required|uuid',
            'client_secret' => 'required|string',
        ]);

        $client = OauthClient::where('id', $request->client_id)
            ->where('is_active', true)
            ->first();

        if (! $client || ! Hash::check($request->client_secret, $client->secret)) {
            return response()->json(['message' => 'Invalid client credentials'], 401);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Attach user to client
        $user->clients()->attach($client->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleExists = Role::where('name', 'super_admin')->exists();
        $admins = $roleExists ? User::role('super_admin')->get() : collect();
        if ($admins->isNotEmpty()) {
            Mail::to($admins)->queue(new NewUserRegistrationAdminMail($user, $client));
        }

        return response()->json([
            'message' => 'User registered successfully. Awaiting approval.',
            'status' => 'pending_approval',
        ], 201);
    }

    /**
     * Invalidate OAuth tokens and destroy central session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Revoke all Passport access tokens
            $user->tokens()->update(['revoked' => true]);

            // Revoke all Passport refresh tokens associated with those access tokens
            $tokenIds = $user->tokens()->pluck('id');
            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $tokenIds)
                ->update(['revoked' => true]);

            // Log the logout event in the audit trail
            event(new Logout('api', $user));
        }

        // Destroy the central browser session via web auth guard
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
