<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientUserController extends Controller
{
    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'client_app_user_id' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $token = $user->token();
        $clientId = $token?->client_id;

        if (! $clientId) {
            return response()->json(['message' => 'Invalid client credentials or token'], 400);
        }

        // Find the pivot for this user + client
        $client = $user->clients()->wherePivot('client_id', $clientId)->first();

        if (! $client && $token->name) {
            $client = $user->clients()->where('name', $token->name)->first();
        }

        $pivot = $client?->pivot;

        if (! $pivot) {
            return response()->json(['message' => 'User is not registered for this application'], 404);
        }

        $pivot->client_app_user_id = $request->input('client_app_user_id');
        $pivot->save();

        return response()->json([
            'message' => 'Client app user linked successfully',
            'client_app_user_id' => $pivot->client_app_user_id,
        ]);
    }
}
