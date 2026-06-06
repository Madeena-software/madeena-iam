<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            return response()->json(['message' => 'Invalid client credentials'], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid user credentials'], 401);
        }

        // Verify client access in pivot table
        $clientUser = $user->clients()->wherePivot('client_id', $client->id)->first();
        if (! $clientUser) {
            return response()->json(['message' => 'User is not authorized for this application'], 403);
        }

        if ($clientUser->pivot->status !== UserStatus::APPROVED) {
            return response()->json(['message' => 'User account is not approved or is suspended for this application'], 403);
        }

        if ($clientUser->pivot->is_blocked) {
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

        return response()->json([
            'message' => 'User registered successfully. Awaiting approval.',
            'status' => 'pending_approval',
        ], 201);
    }
}
