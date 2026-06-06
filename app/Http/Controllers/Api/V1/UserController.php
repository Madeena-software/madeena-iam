<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        // Get the token's client id
        $token = $user->token();
        $clientId = $token ? $token->client_id : null;

        $clientRegistration = null;

        if ($clientId) {
            $pivot = $user->clients()->wherePivot('client_id', $clientId)->first();
            if ($pivot) {
                $clientRegistration = [
                    'status' => $pivot->pivot->status,
                    'is_blocked' => (bool) $pivot->pivot->is_blocked,
                    'client_app_user_id' => $pivot->pivot->client_app_user_id,
                    'approved_at' => $pivot->pivot->approved_at,
                ];
            }
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'avatar_url' => $user->avatar_url,
            'app_registration' => $clientRegistration,
        ]);
    }
}
