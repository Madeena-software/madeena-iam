<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $token = $user->token();
        $clientRegistration = null;

        if ($token) {
            $pivot = $user->clients()->wherePivot('client_id', $token->client_id)->first();

            if (! $pivot && $token->name) {
                $pivot = $user->clients()->where('name', $token->name)->first();
            }

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
            'status' => $clientRegistration ? $clientRegistration['status'] : null,
            'avatar_url' => null,
            'app_registration' => $clientRegistration,
        ]);
    }
}
